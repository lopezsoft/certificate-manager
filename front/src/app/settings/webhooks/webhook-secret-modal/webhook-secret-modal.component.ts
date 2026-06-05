import { Component, Input } from '@angular/core';
import { NgbActiveModal } from '@ng-bootstrap/ng-bootstrap';

@Component({
    selector: 'app-webhook-secret-modal',
    templateUrl: './webhook-secret-modal.component.html',
    styleUrls: ['./webhook-secret-modal.component.scss'],
    standalone: false
})
export class WebhookSecretModalComponent {

  /** Nuevo secreto recibido del servidor luego de rotarlo */
  @Input() secret = '';

  copied = false;

  constructor(public activeModal: NgbActiveModal) { }

  copySecret(): void {
    const setCopiedState = () => {
      this.copied = true;
      setTimeout(() => (this.copied = false), 2500);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(this.secret)
        .then(setCopiedState)
        .catch(err => console.error('Error copying secret: ', err));
    } else {
      const textArea = document.createElement('textarea');
      textArea.value = this.secret;
      textArea.style.position = 'fixed';
      document.body.appendChild(textArea);
      textArea.select();
      try {
        if (document.execCommand('copy')) setCopiedState();
      } catch (err) {}
      document.body.removeChild(textArea);
    }
  }
}
