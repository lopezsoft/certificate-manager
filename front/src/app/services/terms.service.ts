import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map, tap } from 'rxjs/operators';
import { HttpResponsesService } from '../utils';
import { DebugService } from '../utils/debug.service';
import { TermsVersion } from '../interfaces/terms.interface';

/**
 * TermsService — Consulta de la versión vigente de los Términos y Condiciones
 * de MATICERTS.
 *
 * Endpoint consumido (público, no requiere token):
 *   GET /terms/current
 *
 * La versión vigente se consulta al cargar el formulario de creación de
 * solicitud y su `id` se envía como `terms_version_id` en el POST. El backend
 * valida que la versión aceptada sea la vigente y registra la evidencia
 * (fecha, IP y User-Agent) en servidor; el front nunca envía esos datos.
 */
@Injectable({
  providedIn: 'root'
})
export class TermsService {

  /** URL pública de respaldo cuando el backend no informa `source_url`. */
  static readonly FALLBACK_TERMS_URL = 'https://maticerts.com/terminos/';

  private readonly CONTEXT = 'TermsService';

  constructor(
    private http: HttpResponsesService,
    private debug: DebugService,
  ) {}

  /**
   * Obtiene la versión vigente de los Términos y Condiciones.
   * Emite error (404) cuando no hay ninguna versión publicada.
   */
  getCurrent(): Observable<TermsVersion> {
    return this.http.get('/terms/current').pipe(
      map((res: any) => res.dataRecords as TermsVersion),
      tap((terms) => {
        this.debug.log(this.CONTEXT, 'Versión vigente de T&C cargada', {
          id: terms?.id,
          version: terms?.version,
        });
      }),
    );
  }

  /** URL del documento a mostrar al usuario, con respaldo a la URL pública. */
  resolveUrl(terms: TermsVersion | null): string {
    return terms?.source_url || TermsService.FALLBACK_TERMS_URL;
  }
}
