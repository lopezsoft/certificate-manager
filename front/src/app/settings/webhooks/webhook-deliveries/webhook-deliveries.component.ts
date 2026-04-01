import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';

import { BaseComponent } from 'app/@core/components/base/base.component';
import TokenService from 'app/utils/token.service';
import { MessagesService } from 'app/utils';
import { WebhooksService } from 'app/services/webhooks';
import { WebhookDelivery, WebhookEndpoint } from 'app/interfaces';
import {
  WebhookDeliveryStatus,
  WebhookDeliveryStatusLabel,
} from 'app/common/enums/WebhookStatus';

@Component({
  selector: 'app-webhook-deliveries',
  templateUrl: './webhook-deliveries.component.html',
  styleUrls: ['./webhook-deliveries.component.scss']
})
export class WebhookDeliveriesComponent extends BaseComponent implements OnInit {

  webhookId!: number;
  webhook: WebhookEndpoint | null = null;
  deliveries: WebhookDelivery[] = [];
  selected: WebhookDelivery | null = null;
  currentPage = 1;
  totalPages = 1;
  totalRecords = 0;
  perPage = 15;

  readonly statusLabel = WebhookDeliveryStatusLabel;
  readonly DeliveryStatus = WebhookDeliveryStatus;

  constructor(
    public override _token: TokenService,
    public override router: Router,
    public override translate: TranslateService,
    private aRouter: ActivatedRoute,
    private msg: MessagesService,
    private webhooksService: WebhooksService,
  ) {
    super(_token, router, translate);
  }

  override ngOnInit(): void {
    super.ngOnInit();
    this.webhookId = Number(this.aRouter.snapshot.paramMap.get('id'));
    this.loadEndpoint();
    this.loadDeliveries();
  }

  // ─── Carga ────────────────────────────────────────────────────────────────

  loadEndpoint(): void {
    this.webhooksService.getById(this.webhookId).subscribe({
      next: (wh) => (this.webhook = wh),
      error: () => { },
    });
  }

  loadDeliveries(page = 1): void {
    this.activeLoading();
    this.webhooksService
      .getDeliveries(this.webhookId, { page, per_page: this.perPage })
      .subscribe({
        next: (resp: any) => {
          this.deliveries = resp?.data ?? resp ?? [];
          this.totalRecords = resp?.total ?? this.deliveries.length;
          this.totalPages = resp?.last_page ?? 1;
          this.currentPage = resp?.current_page ?? page;
          this.disabledLoading();
        },
        error: () => {
          this.disabledLoading();
          this.msg.toastMessage('Error', 'No se pudo cargar el historial de entregas.', 3);
        },
      });
  }

  // ─── Paginación ───────────────────────────────────────────────────────────

  goToPage(page: number): void {
    if (page < 1 || page > this.totalPages) return;
    this.loadDeliveries(page);
  }

  // ─── Detalle ──────────────────────────────────────────────────────────────

  selectDelivery(delivery: WebhookDelivery): void {
    this.selected = this.selected?.id === delivery.id ? null : delivery;
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────

  badgeClass(status: WebhookDeliveryStatus | string): string {
    const map: Record<string, string> = {
      [WebhookDeliveryStatus.SUCCESS]: 'badge-delivery-success',
      [WebhookDeliveryStatus.FAILED]: 'badge-delivery-failed',
      [WebhookDeliveryStatus.PENDING]: 'badge-delivery-pending',
      [WebhookDeliveryStatus.RETRYING]: 'badge-delivery-retrying',
    };
    return map[status] ?? 'bg-secondary';
  }

  formatPayload(payload: any): string {
    try { return JSON.stringify(payload, null, 2); }
    catch { return String(payload); }
  }

  back(): void {
    this.router.navigate(['/settings/webhooks']);
  }
}
