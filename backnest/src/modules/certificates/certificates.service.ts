import {
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { EventEmitter2 } from '@nestjs/event-emitter';
import { CertificateRequest } from '@database/entities/certificate-request.entity';
import { ChangeHistory } from '@database/entities/change-history.entity';
import { PaginationQueryDto } from '@common/dto/pagination-query.dto';
import { SmartLoggerService } from '@shared/logger/smart-logger.service';
import { CreateCertificateRequestDto } from './dto/create-certificate-request.dto';
import {
  UpdateCertificateRequestDto,
  UpdateCertificateStatusDto,
} from './dto/update-certificate-request.dto';
import { CertificateCreatedEvent } from './events/certificate-created.event';
import { CertificateStatusChangedEvent } from './events/certificate-status-changed.event';

@Injectable()
export class CertificatesService {
  private readonly CONTEXT = 'CertificatesService';

  constructor(
    @InjectRepository(CertificateRequest)
    private readonly certRepo: Repository<CertificateRequest>,
    @InjectRepository(ChangeHistory)
    private readonly historyRepo: Repository<ChangeHistory>,
    private readonly eventEmitter: EventEmitter2,
    private readonly logger: SmartLoggerService,
  ) { }

  async findAll(
    companyId: number,
    query: PaginationQueryDto & {
      request_status?: string;
      start_date?: string;
      end_date?: string;
    },
  ) {
    const page = query.page ?? 1;
    const limit = query.limit ?? 15;
    const skip = (page - 1) * limit;

    const qb = this.certRepo
      .createQueryBuilder('cr')
      .leftJoinAndSelect('cr.identity', 'identity')
      .leftJoinAndSelect('cr.organization', 'organization')
      .leftJoinAndSelect('cr.city', 'city')
      .leftJoinAndSelect('cr.files', 'files')
      .leftJoinAndSelect('cr.history', 'history')
      .where('cr.company_id = :companyId', { companyId })
      .orderBy('cr.id', 'DESC')
      .skip(skip)
      .take(limit);

    if (query.request_status) {
      qb.andWhere('cr.request_status = :status', {
        status: query.request_status,
      });
    }

    if (query.query) {
      qb.andWhere(
        '(cr.company_name ILIKE :q OR cr.dni ILIKE :q OR cr.document_number ILIKE :q)',
        { q: `%${query.query}%` },
      );
    }

    if (query.start_date) {
      qb.andWhere('cr.created_at >= :start', { start: query.start_date });
    }

    if (query.end_date) {
      qb.andWhere('cr.created_at <= :end', { end: query.end_date });
    }

    const [items, totalItems] = await qb.getManyAndCount();

    return {
      __paginated: true as const,
      items,
      meta: {
        currentPage: page,
        totalPages: Math.ceil(totalItems / limit),
        itemsPerPage: limit,
        totalItems,
      },
    };
  }

  async findAllGlobal(
    query: PaginationQueryDto & {
      request_status?: string;
      start_date?: string;
      end_date?: string;
    },
  ) {
    const page = query.page ?? 1;
    const limit = query.limit ?? 15;
    const skip = (page - 1) * limit;

    const qb = this.certRepo
      .createQueryBuilder('cr')
      .leftJoinAndSelect('cr.identity', 'identity')
      .leftJoinAndSelect('cr.organization', 'organization')
      .leftJoinAndSelect('cr.city', 'city')
      .leftJoinAndSelect('cr.files', 'files')
      .orderBy('cr.id', 'DESC')
      .skip(skip)
      .take(limit);

    if (query.request_status) {
      qb.andWhere('cr.request_status = :status', {
        status: query.request_status,
      });
    }

    if (query.query) {
      qb.andWhere('cr.company_name ILIKE :q', { q: `%${query.query}%` });
    }

    if (query.start_date) {
      qb.andWhere('cr.created_at >= :start', { start: query.start_date });
    }

    if (query.end_date) {
      qb.andWhere('cr.created_at <= :end', { end: query.end_date });
    }

    const [items, totalItems] = await qb.getManyAndCount();

    return {
      __paginated: true as const,
      items,
      meta: {
        currentPage: page,
        totalPages: Math.ceil(totalItems / limit),
        itemsPerPage: limit,
        totalItems,
      },
    };
  }

  async findOne(id: number, companyId?: number): Promise<CertificateRequest> {
    const where: any = { id };
    if (companyId) where.companyId = companyId;

    const cert = await this.certRepo.findOne({
      where,
      relations: ['identity', 'organization', 'city', 'files', 'history'],
    });

    if (!cert) {
      throw new NotFoundException('Solicitud de certificado no encontrada.');
    }

    return cert;
  }

  async create(
    dto: CreateCertificateRequestDto,
    userId?: number,
  ): Promise<CertificateRequest> {
    const cert = this.certRepo.create({
      companyId: dto.company_id,
      cityId: dto.city_id,
      identityDocumentId: dto.identity_document_id,
      typeOrganizationId: dto.type_organization_id,
      companyName: dto.company_name,
      dni: dto.dni,
      dv: dto.dv,
      address: dto.address,
      documentNumber: dto.document_number,
      phone: dto.phone,
      mobile: dto.mobile,
      legalRepresentative: dto.legal_representative,
      info: dto.info,
      requestStatus: dto.request_status ?? 'DRAFT',
      postalCode: dto.postal_code,
      life: dto.life,
      documentType: dto.document_type,
      expirationDate: dto.expiration_date
        ? new Date(dto.expiration_date)
        : undefined,
    });

    const saved = await this.certRepo.save(cert);
    this.logger.log(`Certificado creado: ${saved.id}`, this.CONTEXT);

    this.eventEmitter.emit(
      'certificate.created',
      new CertificateCreatedEvent(
        saved.id,
        saved.companyId,
        saved.companyName ?? '',
        userId,
      ),
    );

    return saved;
  }

  async update(
    id: number,
    dto: UpdateCertificateRequestDto,
    companyId?: number,
  ): Promise<CertificateRequest> {
    const cert = await this.findOne(id, companyId);

    Object.assign(cert, {
      ...(dto.city_id !== undefined && { cityId: dto.city_id }),
      ...(dto.identity_document_id !== undefined && {
        identityDocumentId: dto.identity_document_id,
      }),
      ...(dto.type_organization_id !== undefined && {
        typeOrganizationId: dto.type_organization_id,
      }),
      ...(dto.company_name !== undefined && { companyName: dto.company_name }),
      ...(dto.dni !== undefined && { dni: dto.dni }),
      ...(dto.dv !== undefined && { dv: dto.dv }),
      ...(dto.address !== undefined && { address: dto.address }),
      ...(dto.document_number !== undefined && {
        documentNumber: dto.document_number,
      }),
      ...(dto.phone !== undefined && { phone: dto.phone }),
      ...(dto.mobile !== undefined && { mobile: dto.mobile }),
      ...(dto.legal_representative !== undefined && {
        legalRepresentative: dto.legal_representative,
      }),
      ...(dto.info !== undefined && { info: dto.info }),
      ...(dto.postal_code !== undefined && { postalCode: dto.postal_code }),
      ...(dto.life !== undefined && { life: dto.life }),
      ...(dto.document_type !== undefined && {
        documentType: dto.document_type,
      }),
      ...(dto.expiration_date !== undefined && {
        expirationDate: new Date(dto.expiration_date),
      }),
    });

    return this.certRepo.save(cert);
  }

  async updateStatus(
    id: number,
    dto: UpdateCertificateStatusDto,
    userId?: number,
    companyId?: number,
  ): Promise<CertificateRequest> {
    const cert = await this.findOne(id, companyId);
    const previousStatus = cert.requestStatus;

    cert.requestStatus = dto.request_status;
    await this.certRepo.save(cert);

    // Guardar historial de cambio
    await this.historyRepo.save(
      this.historyRepo.create({
        certificateRequestId: id,
        userId,
        userOfChange: userId ? String(userId) : undefined,
        status: dto.request_status,
        comments: dto.comments,
      }),
    );

    this.eventEmitter.emit(
      'certificate.status.changed',
      new CertificateStatusChangedEvent(
        id,
        cert.companyId,
        previousStatus,
        dto.request_status,
        userId,
        dto.comments,
      ),
    );

    this.logger.log(
      `Certificado ${id} cambió de ${previousStatus} → ${dto.request_status}`,
      this.CONTEXT,
    );

    return cert;
  }

  async remove(id: number, companyId?: number): Promise<void> {
    await this.findOne(id, companyId);
    await this.certRepo.delete(id);
    this.logger.log(`Certificado eliminado: ${id}`, this.CONTEXT);
  }

  async sendMail(id: number, companyId?: number): Promise<void> {
    await this.findOne(id, companyId);
    this.logger.log(`Solicitud de envío de correo para certificado ${id}`, this.CONTEXT);
  }
}
