import { Pipe, PipeTransform } from '@angular/core';
import { WebhookHealthStatus, WebhookHealthStatusLabel } from 'app/common/enums/WebhookStatus';

/**
 * Transforma un WebhookHealthStatus en su etiqueta en español.
 *
 * Uso en template:
 *   {{ webhook.health_status | webhookHealth }}
 *
 * Clase CSS sugerida (ver webhooks.component.scss):
 *   [class]="'badge badge-webhook-' + webhook.health_status"
 */
@Pipe({
    name: 'webhookHealth',
    standalone: false
})
export class WebhookHealthPipe implements PipeTransform {
  transform(value: WebhookHealthStatus | string | null | undefined): string {
    if (!value) return WebhookHealthStatusLabel[WebhookHealthStatus.UNKNOWN];
    return WebhookHealthStatusLabel[value as WebhookHealthStatus]
      ?? value;
  }
}
