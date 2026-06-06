import { Component, OnDestroy, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { PricingService } from '../../services/pricing.service';
import { OrderService } from '../../services/order.service';
import { QuotaService } from '../../services/quota.service';
import { PricingTier, PricingCalculation } from '../../interfaces/pricing.interface';
import { OrderResponse } from '../../interfaces/order.interface';
import { Order } from '../../interfaces/order.interface';
import { MessagesService } from '../../utils';
import { LoadMaskService } from '../../services/load-mask.service';
import { FormatsService } from '../../services/formats.service';
import { DebugService } from '../../utils/debug.service';
import { WompiPaymentService } from '../../services/wompi-payment.service';

/**
 * PurchaseComponent — Wizard de compra de certificados.
 *
 * Flujo en 4 pasos:
 *   1. Selección de cantidad + vigencia (con tabla de tarifas)
 *   2. Resumen de precio calculado
 *   3. Confirmación y creación de orden → datos para pago WOMPI
 *   4. Pago y polling de estado → confirmación final
 */
@Component({
    selector: 'app-purchase',
    templateUrl: './purchase.component.html',
    styleUrl: './purchase.component.scss',
    standalone: false
})
export class PurchaseComponent implements OnInit, OnDestroy {

  /** Paso actual del wizard (1-4) */
  currentStep = 1;

  /** Datos */
  tiers: PricingTier[] = [];
  priceCalculation: PricingCalculation | null = null;
  orderResponse: OrderResponse | null = null;
  paymentStatus: string = '';

  /** Formularios */
  purchaseForm: FormGroup;

  /** Estados */
  loadingTiers = false;
  loadingPrice = false;
  creatingOrder = false;
  polling = false;

  private readonly destroy$ = new Subject<void>();

  constructor(
    private fb: FormBuilder,
    private pricingService: PricingService,
    private orderService: OrderService,
    private quotaService: QuotaService,
    private msg: MessagesService,
    private mask: LoadMaskService,
    public format: FormatsService,
    private router: Router,
    private debug: DebugService,
    private wompiPaymentService: WompiPaymentService,
  ) {
    this.purchaseForm = this.fb.group({
      quantity: [1, [Validators.required, Validators.min(1)]],
      vigencia: [1, [Validators.required]],
    });
  }

  ngOnInit(): void {
    this.loadTiers();

    // Escuchar cuando el pago se confirma desde el modal global
    this.wompiPaymentService.paymentCompleted$
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        this.polling = false;
        this.paymentStatus = 'PAID';
        this.currentStep = 4;
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ─── Paso 1: Cargar tarifas ──────────────────────────────────────────────

  loadTiers(): void {
    this.loadingTiers = true;
    this.pricingService.getTiers().subscribe({
      next: (tiers) => {
        this.tiers = tiers;
        this.loadingTiers = false;
      },
      error: (err) => {
        this.debug.error('PurchaseComponent', 'Error al cargar tarifas', err);
        this.loadingTiers = false;
        this.msg.errorMessage('Error', 'No se pudieron cargar las tarifas. Intente nuevamente.');
      }
    });
  }

  // ─── Paso 2: Calcular precio ─────────────────────────────────────────────

  onCalculatePrice(): void {
    const { quantity, vigencia } = this.purchaseForm.getRawValue();

    if (quantity < 1) {
      this.msg.errorMessage('Error', 'La cantidad debe ser al menos 1.');
      return;
    }

    this.loadingPrice = true;
    this.pricingService.calculatePrice(quantity, vigencia).subscribe({
      next: (calc) => {
        this.priceCalculation = calc;
        this.loadingPrice = false;
        this.currentStep = 2;
      },
      error: (err) => {
        this.debug.error('PurchaseComponent', 'Error al calcular precio', err);
        this.loadingPrice = false;
        this.msg.errorMessage('Error', 'No se pudo calcular el precio. Intente nuevamente.');
      }
    });
  }

  // ─── Paso 3: Crear orden ─────────────────────────────────────────────────

  onCreateOrder(): void {
    const { quantity, vigencia } = this.purchaseForm.getRawValue();
    this.creatingOrder = true;
    this.mask.showBlockUI('Creando orden de compra...');

    this.orderService.createOrder({ quantity, vigencia }).subscribe({
      next: (order) => {
        this.orderResponse = order;
        this.creatingOrder = false;
        this.mask.hideBlockUI();
        this.currentStep = 3;
      },
      error: (err) => {
        this.debug.error('PurchaseComponent', 'Error al crear orden', err);
        this.creatingOrder = false;
        this.mask.hideBlockUI();
        this.msg.errorMessage('Error', 'No se pudo crear la orden. Intente nuevamente.');
      }
    });
  }

  // ─── Paso 4: Pago con Widget de WOMPI ─────────────────────────────────────

  /**
   * Delega el pago al WompiPaymentService que gestiona el modal global.
   * Suscrito a paymentCompleted$ para actualizar el estado del wizard.
   */
  onPayWithWompi(): void {
    if (!this.orderResponse) return;
    this.wompiPaymentService.openPayment(this.orderResponse);
  }

  // ─── Navegación ──────────────────────────────────────────────────────────

  goToStep(step: number): void {
    if (step < this.currentStep) {
      this.currentStep = step;
    }
  }

  goToOrders(): void {
    this.router.navigate(['/orders']);
  }

  goToRequests(): void {
    this.router.navigate(['/requests/list']);
  }

  // ─── Utilidades ──────────────────────────────────────────────────────────

  formatCurrency(value: number, currency: string = 'COP'): string {
    return this.format.getCurrencyFormat('es-CO', currency, value);
  }

  /** Obtiene el precio unitario de referencia para la cantidad actual */
  getSelectedTierPrice(): string {
    const { quantity, vigencia } = this.purchaseForm.getRawValue();
    const tier = this.tiers.find(t => quantity >= t.min && (t.max === null || quantity <= t.max));
    if (!tier) return '';
    const price = vigencia === 2 ? tier.price_2yr : tier.price_1yr;
    return this.formatCurrency(price);
  }
}
