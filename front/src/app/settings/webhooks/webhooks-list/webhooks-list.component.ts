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
  styleUrls: ['./webhooks-list.component.scss']
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
      next: (data: any) => {
        this.webhooks = Array.isArray(data) ? data : data?.data ?? [];
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
          const secret = resp?.secret ?? '';
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
}
