import {
  BadRequestException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { createHmac } from 'crypto';
import { IsNull, Repository } from 'typeorm';
import { ConfigService } from '@nestjs/config';
import { WebhookEndpoint } from '@database/entities/webhook-endpoint.entity';
import {
  WebhookDelivery,
  WebhookDeliveryStatus,
} from '@database/entities/webhook-delivery.entity';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';
import { CreateWebhookEndpointDto } from './dto/create-webhook-endpoint.dto';
import { UpdateWebhookEndpointDto } from './dto/update-webhook-endpoint.dto';

@Injectable()
export class WebhooksService {
  private readonly CONTEXT = 'WebhooksService';
  private readonly maxFailures: number;
  private readonly timeout: number;

  constructor(
    @InjectRepository(WebhookEndpoint)
    private readonly endpointRepo: Repository<WebhookEndpoint>,
    @InjectRepository(WebhookDelivery)
    private readonly deliveryRepo: Repository<WebhookDelivery>,
    private readonly configService: ConfigService,
    private readonly logger: SmartLoggerService,
  ) {
    this.maxFailures = this.configService.get<number>(
      'webhooks.maxFailures',
      10,
    );
    this.timeout = this.configService.get<number>('webhooks.timeout', 10000);
  }

  // ─── Endpoints CRUD ─────────────────────────────────────────────────────────

  async findAll(companyId: number): Promise<WebhookEndpoint[]> {
    return this.endpointRepo.find({
      where: { companyId, deletedAt: IsNull() },
      order: { id: 'DESC' },
    });
  }

  async findOne(id: number, companyId?: number): Promise<WebhookEndpoint> {
    const where: any = { id, deletedAt: IsNull() };
    if (companyId) where.companyId = companyId;

    const endpoint = await this.endpointRepo.findOne({ where });
    if (!endpoint) {
      throw new NotFoundException('Webhook endpoint no encontrado.');
    }
    return endpoint;
  }

  async create(
    companyId: number,
    dto: CreateWebhookEndpointDto,
  ): Promise<WebhookEndpoint> {
    const maxPerCompany = this.configService.get<number>(
      'webhooks.maxEndpointsPerCompany',
      5,
    );

    const count = await this.endpointRepo.count({
      where: { companyId, deletedAt: IsNull() },
    });

    if (count >= maxPerCompany) {
      throw new BadRequestException(
        `Solo se permiten hasta ${maxPerCompany} endpoints por empresa.`,
      );
    }

    const endpoint = this.endpointRepo.create({
      companyId,
      url: dto.url,
      secret: dto.secret,
      events: dto.events ?? [],
      isActive: dto.is_active ?? true,
      description: dto.description,
      failureCount: 0,
    });

    const saved = await this.endpointRepo.save(endpoint);
    this.logger.log(`Webhook endpoint creado: ${saved.id}`, this.CONTEXT);
    return saved;
  }

  async update(
    id: number,
    companyId: number,
    dto: UpdateWebhookEndpointDto,
  ): Promise<WebhookEndpoint> {
    const endpoint = await this.findOne(id, companyId);

    Object.assign(endpoint, {
      ...(dto.url !== undefined && { url: dto.url }),
      ...(dto.secret !== undefined && { secret: dto.secret }),
      ...(dto.events !== undefined && { events: dto.events }),
      ...(dto.is_active !== undefined && { isActive: dto.is_active }),
      ...(dto.description !== undefined && { description: dto.description }),
    });

    return this.endpointRepo.save(endpoint);
  }

  async remove(id: number, companyId: number): Promise<void> {
    const endpoint = await this.findOne(id, companyId);
    endpoint.deletedAt = new Date();
    await this.endpointRepo.save(endpoint);
    this.logger.log(`Webhook endpoint eliminado: ${id}`, this.CONTEXT);
  }

  // ─── Deliveries ─────────────────────────────────────────────────────────────

  async getDeliveries(endpointId: number): Promise<WebhookDelivery[]> {
    return this.deliveryRepo.find({
      where: { webhookEndpointId: endpointId },
      order: { id: 'DESC' },
      take: 50,
    });
  }

  getAvailableEvents(): string[] {
    return [
      'certificate.created',
      'certificate.status.changed',
      'certificate.deleted',
      'certificate.file.uploaded',
      '*',
    ];
  }

  async rotateSecret(id: number, companyId: number): Promise<WebhookEndpoint> {
    const endpoint = await this.findOne(id, companyId);
    endpoint.secret = createHmac('sha256', `${Date.now()}-${id}`)
      .update(Math.random().toString())
      .digest('hex');

    return this.endpointRepo.save(endpoint);
  }

  // ─── Dispatch ────────────────────────────────────────────────────────────────

  /**
   * Envía un evento a todos los endpoints activos de una empresa que escuchan ese evento.
   */
  async dispatch(
    companyId: number,
    eventType: string,
    payload: Record<string, any>,
  ): Promise<void> {
    const endpoints = await this.endpointRepo.find({
      where: { companyId, isActive: true, deletedAt: IsNull() },
      select: ['id', 'url', 'secret', 'events', 'failureCount'],
    });

    const eligible = endpoints.filter(
      (ep) =>
        !ep.events ||
        ep.events.length === 0 ||
        ep.events.includes(eventType) ||
        ep.events.includes('*'),
    );

    await Promise.allSettled(
      eligible.map((ep) => this.sendToEndpoint(ep, eventType, payload)),
    );
  }

  private async sendToEndpoint(
    endpoint: WebhookEndpoint,
    eventType: string,
    payload: Record<string, any>,
  ): Promise<void> {
    const body = JSON.stringify({
      event: eventType,
      data: payload,
      timestamp: new Date().toISOString(),
    });

    const signature = this.buildSignature(endpoint.secret, body);

    const delivery = this.deliveryRepo.create({
      webhookEndpointId: endpoint.id,
      eventType,
      payload,
      signature,
      status: WebhookDeliveryStatus.PENDING,
      attempt: 1,
    });

    await this.deliveryRepo.save(delivery);

    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(
        () => controller.abort(),
        this.timeout,
      );

      const response = await fetch(endpoint.url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Webhook-Signature': signature,
          'X-Webhook-Event': eventType,
        },
        body,
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      delivery.httpStatus = response.status;
      delivery.responseBody = await response.text().catch(() => '');
      delivery.status = response.ok
        ? WebhookDeliveryStatus.DELIVERED
        : WebhookDeliveryStatus.FAILED;
      if (response.ok) delivery.deliveredAt = new Date();

      if (response.ok) {
        endpoint.failureCount = 0;
        endpoint.lastTriggeredAt = new Date();
      } else {
        endpoint.failureCount += 1;
        if (endpoint.failureCount >= this.maxFailures) {
          endpoint.isActive = false;
          this.logger.warn(
            `Endpoint ${endpoint.id} desactivado por ${this.maxFailures} fallos consecutivos.`,
            this.CONTEXT,
          );
        }
        await this.endpointRepo.save(endpoint);
      }
    } catch (err) {
      delivery.status = WebhookDeliveryStatus.FAILED;
      delivery.responseBody = (err as Error).message;
      endpoint.failureCount += 1;
      await this.endpointRepo.save(endpoint);

      this.logger.error(
        `Error enviando webhook a ${endpoint.url}: ${(err as Error).message}`,
        undefined,
        this.CONTEXT,
      );
    }

    await this.deliveryRepo.save(delivery);
  }

  private buildSignature(secret: string, body: string): string {
    return createHmac('sha256', secret).update(body).digest('hex');
  }
}
