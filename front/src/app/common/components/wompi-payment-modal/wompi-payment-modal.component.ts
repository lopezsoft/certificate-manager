import { Component, OnDestroy, OnInit } from '@angular/core';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { WompiPaymentService, WompiPaymentState } from '../../../services/wompi-payment.service';
import { FormatsService } from '../../../services/formats.service';

/**
 * WompiPaymentModalComponent — Modal reutilizable de pago con Wompi.
 *
 * Se controla exclusivamente vía WompiPaymentService.
 * Cualquier componente puede abrir este modal con:
 *   - wompiPaymentService.openPayment(orderData)  → pago nuevo
 *   - wompiPaymentService.retryPayment(orderId)    → reintento
 *
 * Debe incluirse UNA SOLA VEZ en el layout principal (app.component.html):
 *   <app-wompi-payment-modal></app-wompi-payment-modal>
 */
@Component({
    selector: 'app-wompi-payment-modal',
    templateUrl: './wompi-payment-modal.component.html',
    styleUrls: ['./wompi-payment-modal.component.scss'],
    standalone: false
})
export class WompiPaymentModalComponent implements OnInit, OnDestroy {

  state: WompiPaymentState = {
    visible: false,
    orderData: null,
    loading: false,
    polling: false,
    paymentStatus: '',
    orderUuid: null,
  };

  private readonly destroy$ = new Subject<void>();

  constructor(
    public wompiService: WompiPaymentService,
    public format: FormatsService,
  ) {}

  ngOnInit(): void {
    this.wompiService.state$
      .pipe(takeUntil(this.destroy$))
      .subscribe(state => {
        this.state = state;
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  onPayClick(): void {
    this.wompiService.openWidget();
  }

  onClose(): void {
    this.wompiService.close();
  }

  formatCurrency(value: number | string, currency: string = 'COP'): string {
    return this.format.getCurrencyFormat('es-CO', currency, Number(value));
  }
}
