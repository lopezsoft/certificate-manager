import { Injectable, NgZone } from '@angular/core';

import { BehaviorSubject } from 'rxjs';

/**
 * Mapa de breakpoints estándar Bootstrap 5 + aliases de Vuexy/flex-layout.
 * Reemplaza MediaObserver de @angular/flex-layout con window.matchMedia nativo.
 */
const BREAKPOINT_MAP: Record<string, string> = {
  'xs':       '(max-width: 575.98px)',
  'sm':       '(min-width: 576px) and (max-width: 767.98px)',
  'md':       '(min-width: 768px) and (max-width: 991.98px)',
  'lg':       '(min-width: 992px) and (max-width: 1199.98px)',
  'xl':       '(min-width: 1200px) and (max-width: 1399.98px)',
  'xxl':      '(min-width: 1400px)',
  'lt-sm':    '(max-width: 575.98px)',
  'lt-md':    '(max-width: 767.98px)',
  'lt-lg':    '(max-width: 991.98px)',
  'lt-xl':    '(max-width: 1199.98px)',
  'gt-xs':    '(min-width: 576px)',
  'gt-sm':    '(min-width: 768px)',
  'gt-md':    '(min-width: 992px)',
  'gt-lg':    '(min-width: 1200px)',
  'gt-xl':    '(min-width: 1400px)',
  'bs-gt-xl': '(min-width: 1200px)',
};

@Injectable({
  providedIn: 'root'
})
export class CoreMediaService {
  currentMediaQuery: string;
  onMediaUpdate: BehaviorSubject<string> = new BehaviorSubject<string>('');

  constructor(private _ngZone: NgZone) {
    this.currentMediaQuery = '';
    this._init();
  }

  /**
   * Verifica si un alias de breakpoint está activo.
   * Reemplaza MediaObserver.isActive() de @angular/flex-layout.
   */
  isActive(alias: string): boolean {
    const query = BREAKPOINT_MAP[alias];
    if (!query) {
      return false;
    }
    return window.matchMedia(query).matches;
  }

  private _init(): void {
    // Detectar el alias activo actual
    const currentAlias = this._getActiveAlias();
    this.currentMediaQuery = currentAlias;
    this.onMediaUpdate.next(currentAlias);

    // Escuchar cambios de tamaño de ventana
    window.addEventListener('resize', () => {
      this._ngZone.run(() => {
        const alias = this._getActiveAlias();
        if (alias !== this.currentMediaQuery) {
          this.currentMediaQuery = alias;
          this.onMediaUpdate.next(alias);
        }
      });
    });
  }

  private _getActiveAlias(): string {
    const primaryAliases = ['xxl', 'xl', 'lg', 'md', 'sm', 'xs'];
    for (const alias of primaryAliases) {
      if (this.isActive(alias)) {
        return alias;
      }
    }
    return 'xs';
  }
}
