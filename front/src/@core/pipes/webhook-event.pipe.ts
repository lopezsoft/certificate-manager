import { Pipe, PipeTransform } from '@angular/core';
import { WebhookEventLabel } from 'app/common/enums/WebhookStatus';

/**
 * Transforma un valor técnico de evento webhook en su etiqueta legible.
 *
 * Uso en template:
 *   {{ 'certificate.created' | webhookEvent }}  →  'Certificado creado'
 */
@Pipe({
    name: 'webhookEvent',
    standalone: false
})
export class WebhookEventPipe implements PipeTransform {
  transform(value: string | null | undefined): string {
    if (!value) return '—';
    return WebhookEventLabel[value] ?? value;
  }
}
