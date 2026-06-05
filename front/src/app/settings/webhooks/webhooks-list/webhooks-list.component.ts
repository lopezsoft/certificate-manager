import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';

import { BaseComponent } from 'app/@core/components/base/base.component';
import TokenService from 'app/utils/token.service';
import { MessagesService } from 'app/utils';
import { WebhooksService } from 'app/services/webhooks';
import { WebhookEndpoint } from 'app/interfaces';
import { WebhookSecretModalComponent } from '../webhook-secret-modal/webhook-secret-modal.component';

@Component({
    selector: 'app-webhooks-list',
    templateUrl: './webhooks-list.component.html',
    styleUrls: ['./webhooks-list.component.scss'],
    standalone: false
})
export class WebhooksListComponent extends BaseComponent implements OnInit {

  webhooks: WebhookEndpoint[] = [];

  constructor(
    public override _token: TokenService,
    public override router: Router,
    public override translate: TranslateService,
    private modal: NgbModal,
    private msg: MessagesService,
    private webhooksService: WebhooksService,
  ) {
    super(_token, router, translate);
  }

  override ngOnInit(): void {
    super.ngOnInit();
    this.loadWebhooks();
  }

  // ─── Carga ────────────────────────────────────────────────────────────────

  loadWebhooks(): void {
    this.activeLoading();
    this.webhooksService.getAll().subscribe({
      next: (resp: any) => {
        // Backend: { dataRecords: { data: [...], total, per_page, ... }, success: true }
        const records = resp?.dataRecords;
        this.webhooks = records?.data ?? (Array.isArray(records) ? records : []);
        this.disabledLoading();
      },
      error: (err) => {
        this.disabledLoading();
        this.msg.toastMessage('Error', 'No se pudieron cargar los webhooks.', 3);
      },
    });
  }

  // ─── Navegación ───────────────────────────────────────────────────────────

  newWebhook(): void {
    this.router.navigate(['/settings/webhooks/new']);
  }

  editWebhook(id: number): void {
    this.router.navigate([`/settings/webhooks/${id}/edit`]);
  }

  viewDeliveries(id: number): void {
    this.router.navigate([`/settings/webhooks/${id}/deliveries`]);
  }

  // ─── Acciones ─────────────────────────────────────────────────────────────

  toggleActive(webhook: WebhookEndpoint): void {
    const next = !webhook.is_active;
    this.webhooksService.toggleActive(webhook.id, next).subscribe({
      next: () => {
        webhook.is_active = next;
        this.msg.toastMessage(
          'Webhook',
          next ? 'Webhook activado.' : 'Webhook desactivado.',
          1,
        );
      },
      error: () => this.msg.toastMessage('Error', 'No se pudo actualizar el webhook.', 3),
    });
  }

  rotateSecret(webhook: WebhookEndpoint): void {
    this.msg.confirm(
      '¿Rotar secreto?',
      'Se generará un nuevo secreto. El anterior dejará de funcionar de inmediato.',
    ).then((result: any) => {
      if (!result.isConfirmed) return;
      this.webhooksService.rotateSecret(webhook.id).subscribe({
        next: (resp: any) => {
          const data = resp?.dataRecords ?? resp;
          const secret = data?.secret ?? '';
          const ref = this.modal.open(WebhookSecretModalComponent, {
            size: 'lg',
            backdrop: 'static',
            centered: true,
          });
          ref.componentInstance.secret = secret;
        },
        error: () => this.msg.toastMessage('Error', 'No se pudo rotar el secreto.', 3),
      });
    });
  }

  deleteWebhook(webhook: WebhookEndpoint): void {
    this.msg.confirm(
      '¿Eliminar webhook?',
      `Se eliminará el endpoint <strong>${webhook.url}</strong> de forma permanente.`,
    ).then((result: any) => {
      if (!result.isConfirmed) return;
      this.webhooksService.delete(webhook.id).subscribe({
        next: () => {
          this.webhooks = this.webhooks.filter(w => w.id !== webhook.id);
          this.msg.toastMessage('Webhook', 'Webhook eliminado correctamente.', 1);
        },
        error: () => this.msg.toastMessage('Error', 'No se pudo eliminar el webhook.', 3),
      });
    });
  }
  // ─── UX / Helpers ─────────────────────────────────────────────────────────

  extractDomain(url: string): string {
    if (!url) return '—';
    try {
      return new URL(url).hostname.toLowerCase();
    } catch {
      return url.toLowerCase();
    }
  }

  getHiddenEventsTooltip(events: string[]): string {
    return events.slice(2).join(', ');
  }

  copyUrl(url: string, event: Event): void {
    event.stopPropagation();
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url)
        .then(() => this.msg.toastMessage('URL copiada', '', 1))
        .catch(() => this.msg.toastMessage('Error', 'No se pudo copiar', 3));
    } else {
      const ta = document.createElement('textarea');
      ta.value = url;
      ta.style.position = 'fixed';
      document.body.appendChild(ta);
      ta.select();
      try {
        if (document.execCommand('copy')) {
          this.msg.toastMessage('URL copiada', '', 1);
        }
      } catch (err) {}
      document.body.removeChild(ta);
    }
  }
}
