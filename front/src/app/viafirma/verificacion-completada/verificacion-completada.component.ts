import { Component, OnDestroy, OnInit } from '@angular/core';
import { takeUntil } from 'rxjs/operators';
import { Subject } from 'rxjs';

import { CoreConfigService } from '@core/services/config.service';

/**
 * Página pública de aterrizaje tras completar la verificación de identidad
 * (KYC) en MetaMap. El backend redirige aquí sin sesión ni parámetros —
 * ver VIAFIRMA_KYC_COMPLETED_PATH del backend. No consulta ninguna API: solo
 * informa que el paso de verificación se registró, sin asegurar el resultado
 * final del trámite.
 */
@Component({
  selector: 'app-verificacion-completada',
  templateUrl: './verificacion-completada.component.html',
  styleUrls: ['./verificacion-completada.component.scss'],
  standalone: false
})
export class VerificacionCompletadaComponent implements OnInit, OnDestroy {
  public coreConfig: any;

  private _unsubscribeAll: Subject<any>;

  constructor(private _coreConfigService: CoreConfigService) {
    this._unsubscribeAll = new Subject();

    this._coreConfigService.config = {
      layout: {
        navbar: {
          hidden: true
        },
        footer: {
          hidden: true
        },
        menu: {
          hidden: true
        },
        customizer: false,
        enableLocalStorage: false
      }
    };
  }

  ngOnInit(): void {
    this._coreConfigService.config.pipe(takeUntil(this._unsubscribeAll)).subscribe(config => {
      this.coreConfig = config;
    });
  }

  ngOnDestroy(): void {
    this._unsubscribeAll.next(true);
    this._unsubscribeAll.complete();
  }

  closeWindow(): void {
    window.close();
    setTimeout(() => {
      // Si aún podemos ejecutar código, significa que close() falló — abre Google
      window.open('https://www.google.com', '_self');
    }, 500);
  }
}
