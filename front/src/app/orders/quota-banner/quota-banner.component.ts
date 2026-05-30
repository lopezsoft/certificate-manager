import { Component, OnInit } from '@angular/core';
import { QuotaService } from '../../services/quota.service';
import { QuotaStatus } from '../../interfaces/quota.interface';
import { DebugService } from '../../utils/debug.service';

/**
 * QuotaBannerComponent — Banner reutilizable que muestra el estado de cupo.
 *
 * Se usa en dashboard, certificate-request y cualquier vista
 * que necesite mostrar la disponibilidad de certificados.
 */
@Component({
  selector: 'app-quota-banner',
  templateUrl: './quota-banner.component.html',
  styleUrl: './quota-banner.component.scss'
})
export class QuotaBannerComponent implements OnInit {

  quota: QuotaStatus | null = null;
  loading = true;
  error = false;

  constructor(
    private quotaService: QuotaService,
    private debug: DebugService,
  ) {}

  ngOnInit(): void {
    this.loadQuota();
  }

  loadQuota(): void {
    this.loading = true;
    this.error = false;
    this.quotaService.getQuotaStatus().subscribe({
      next: (quota) => {
        this.quota = quota;
        this.loading = false;
      },
      error: (err) => {
        this.debug.error('QuotaBannerComponent', 'Error al obtener cupo', err);
        this.loading = false;
        this.error = true;
      }
    });
  }

  get totalAvailable(): number {
    if (!this.quota) return 0;
    return this.quota.prepaid_items_available + (this.quota.postpaid?.remaining ?? 0);
  }

  get hasQuota(): boolean {
    return this.quota?.has_quota ?? false;
  }

  get postpaidExpiresAt(): string | null {
    return this.quota?.postpaid?.expires_at ?? null;
  }
}
