import { Component, OnDestroy, OnInit } from '@angular/core';
import { QuotaService } from '../../services/quota.service';
import { QuotaStatus } from '../../interfaces/quota.interface';
import { Subscription } from 'rxjs';

/**
 * QuotaBannerComponent — Banner reutilizable que muestra el estado de cupo.
 *
 * Se suscribe al observable global `QuotaService.quotaStatus$`
 * sin hacer llamadas HTTP propias.
 */
@Component({
    selector: 'app-quota-banner',
    templateUrl: './quota-banner.component.html',
    styleUrl: './quota-banner.component.scss',
    standalone: false
})
export class QuotaBannerComponent implements OnInit, OnDestroy {

  quota: QuotaStatus | null = null;
  loading = true;
  error = false;

  private quotaSub: Subscription | null = null;

  constructor(
    public quotaService: QuotaService,
  ) {}

  ngOnInit(): void {
    this.quotaSub = this.quotaService.quotaStatus$.subscribe((status) => {
      if (status) {
        this.quota = status;
        this.loading = false;
        this.error = false;
      }
    });
  }

  ngOnDestroy(): void {
    this.quotaSub?.unsubscribe();
  }

  get totalAvailable(): number {
    return this.quotaService.totalAvailable;
  }

  get hasQuota(): boolean {
    return this.quotaService.hasQuota;
  }

  get postpaidExpiresAt(): string | null {
    return this.quota?.postpaid?.expires_at ?? null;
  }
}
