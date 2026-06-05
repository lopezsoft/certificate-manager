import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { animate, style, transition, trigger } from '@angular/animations';

/**
 * ModalCardComponent — Modal reutilizable basado en NotificationCardComponent.
 *
 * Overlay full-screen con backdrop, contenido proyectado vía ng-content,
 * título configurable, y botones de acción (confirmar/cancelar).
 *
 * Uso:
 *   <app-modal-card
 *     [visible]="showModal"
 *     [title]="'Mi Título'"
 *     [confirmText]="'Guardar'"
 *     [cancelText]="'Cancelar'"
 *     [loading]="saving"
 *     (onConfirm)="save()"
 *     (onClose)="showModal = false">
 *       <!-- contenido del modal -->
 *   </app-modal-card>
 */
@Component({
    selector: 'app-modal-card',
    templateUrl: './modal-card.component.html',
    styleUrls: ['./modal-card.component.scss'],
    animations: [
        trigger('fadeInOut', [
            transition(':enter', [
                style({ opacity: 0 }),
                animate('300ms', style({ opacity: 1 })),
            ]),
            transition(':leave', [
                animate('300ms', style({ opacity: 0 })),
            ])
        ])
    ],
    standalone: false
})
export class ModalCardComponent {

  @Input() visible = false;
  @Input() title = '';
  @Input() confirmText = 'Guardar';
  @Input() cancelText = 'Cancelar';
  @Input() loading = false;
  @Input() showFooter = true;
  @Input() size: 'sm' | 'md' | 'lg' = 'md';

  @Output() onConfirm = new EventEmitter<void>();
  @Output() onClose = new EventEmitter<void>();

  protected close(): void {
    if (!this.loading) {
      this.onClose.emit();
    }
  }

  protected confirm(): void {
    this.onConfirm.emit();
  }
}
