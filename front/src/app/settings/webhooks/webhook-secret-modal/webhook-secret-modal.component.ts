import { Component, Input } from '@angular/core';
import { NgbActiveModal } from '@ng-bootstrap/ng-bootstrap';

@Component({
  selector: 'app-webhook-secret-modal',
  templateUrl: './webhook-secret-modal.component.html',
  styleUrls: ['./webhook-secret-modal.component.scss']
})
export class WebhookSecretModalComponent {

  /** Nuevo secreto recibido del servidor luego de rotarlo */
  @Input() secret = '';

  copied = false;

  constructor(public activeModal: NgbActiveModal) { }

  copySecret(): void {
    navigator.clipboard.writeText(this.secret).then(() => {
      this.copied = true;
      setTimeout(() => (this.copied = false), 2500);
    });
  }
}
