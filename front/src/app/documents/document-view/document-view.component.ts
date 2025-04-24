import {Component, EventEmitter, Output} from '@angular/core';
import {animate, style, transition, trigger} from "@angular/animations";
import {ShippingService} from "../../services/shipping.service";
import {Shipping} from "../../interfaces/shipping-intetface";
import {FormatsService} from "../../services/formats.service";
import {EmailLogsService} from "../../services/email-logs.service";

@Component({
  selector: 'app-document-view',
  templateUrl: './document-view.component.html',
  styleUrl: './document-view.component.scss',
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
  ]
})
export class DocumentViewComponent {
  @Output() onOpenDocument = new EventEmitter<Shipping>();
  @Output() onSendMail = new EventEmitter<Shipping>();
  @Output() onGenDocument = new EventEmitter<{
    shipping: Shipping,
    path: string
  }>();
  @Output() onStatusDocument = new EventEmitter<Shipping>();
  constructor(
    public shipping: ShippingService,
    public format: FormatsService,
    public emailLogs: EmailLogsService
  ) {
  }
  
  initData() {
    this.emailLogs.emailLogs = [];
    this.emailLogs.findByDocumentId(this.currentShipping.id, {
      type_document_id: this.currentShipping.type_document_id,
    })
      .subscribe();
  }
  
  public get currentShipping(): Shipping {
    return this.shipping.currentShipping;
  }
  
  protected sendEmail() {
    this.onSendMail.emit(this.currentShipping);
  }
  
  protected openDocument() {
    this.onOpenDocument.emit(this.currentShipping);
  }
  
  getCurrency() {
    return this.currentShipping.jsonData?.currency ?? null;
  }
  
  getDocument(path: string) {
    this.onGenDocument.emit({
      shipping: this.currentShipping,
      path: path
    });
  }
  
  statusDocument() {
    this.onStatusDocument.emit(this.currentShipping);
  }

  deleteDocument() {
    this.shipping.deleteDocument(this.currentShipping.id)
      .subscribe({
        next: () => {
          this.shipping.shippingData = this.shipping.shippingData.filter((row) => row.id !== this.currentShipping.id);
          this.shipping.currentShipping = null;
        }
      });
  }

  protected canSendEmail() {
    const currentShipping = this.currentShipping;
    return (currentShipping?.jsonData?.customer && currentShipping.is_valid &&
        currentShipping.jsonData?.customer.email && currentShipping.jsonData?.customer?.dni !== '222222222222');
  }
}
