import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { HttpResponsesService } from '../utils';
import { PricingTier, PricingCalculation } from '../interfaces/pricing.interface';
import { DebugService } from '../utils/debug.service';

/**
 * PricingService — Consulta de tarifas y cálculo de precios.
 *
 * Endpoints consumidos:
 *   GET /pricing              → Lista de franjas tarifarias
 *   GET /pricing?quantity&vigencia → Cálculo de precio exacto
 */
@Injectable({
  providedIn: 'root'
})
export class PricingService {

  constructor(
    private http: HttpResponsesService,
    private debug: DebugService,
  ) {}

  /**
   * Obtiene la lista de franjas tarifarias.
   */
  getTiers(): Observable<PricingTier[]> {
    return this.http.get('/pricing').pipe(
      map((res) => {
        const tiers = res.dataRecords.data;
        this.debug.log('PricingService', 'Tarifas obtenidas', tiers);
        return tiers;
      }),
    );
  }

  /**
   * Calcula el precio exacto para una cantidad y vigencia dada.
   * @param quantity Cantidad de certificados (≥ 1).
   * @param vigencia Vigencia en años: 1 o 2.
   */
  calculatePrice(quantity: number, vigencia: number): Observable<PricingCalculation> {
    return this.http.get('/pricing', { quantity, vigencia }).pipe(
      map((res: any) => {
        const calc = res.dataRecords.data as PricingCalculation;
        this.debug.log('PricingService', 'Precio calculado', calc);
        return calc;
      }),
    );
  }
}
