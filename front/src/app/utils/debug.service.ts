import { Injectable } from '@angular/core';
import { environment } from '../../environments/environment';

/**
 * DebugService — Servicio centralizado de logging para el entorno Angular.
 *
 * Principios aplicados:
 *  - Single Responsibility: único punto de logging en el sistema.
 *  - Open/Closed: extensible para agregar transports (ej. Sentry) sin modificar consumidores.
 *  - Security by Design: suprime todos los logs en producción automáticamente.
 *
 * USO:
 *   // Inyectar en el constructor
 *   constructor(private debug: DebugService) {}
 *
 *   // Usar en lugar de console.log
 *   this.debug.log('WebhookService', 'Lista cargada', datos);
 *   this.debug.warn('FormComponent', 'Campo vacío');
 *   this.debug.error('AuthInterceptor', 'Token inválido', error);
 */
@Injectable({
  providedIn: 'root'
})
export class DebugService {

  /** `true` solo en entornos no-productivos */
  private readonly isEnabled: boolean = !environment.production;

  // ─── Métodos públicos de logging ───────────────────────────────────────────

  /**
   * Log informativo. Equivalente a `console.log`.
   * @param context  Nombre del componente o servicio que emite el log.
   * @param message  Mensaje descriptivo.
   * @param data     Datos opcionales adicionales (nunca datos sensibles).
   */
  log(context: string, message: string, ...data: any[]): void {
    if (!this.isEnabled) return;
    console.log(`%c[${context}]`, 'color: #2556a3; font-weight: bold;', message, ...data);
  }

  /**
   * Log de advertencia. Equivalente a `console.warn`.
   * @param context  Nombre del componente o servicio que emite el log.
   * @param message  Mensaje descriptivo.
   * @param data     Datos opcionales adicionales.
   */
  warn(context: string, message: string, ...data: any[]): void {
    if (!this.isEnabled) return;
    console.warn(`%c[${context}]`, 'color: #FF9F43; font-weight: bold;', message, ...data);
  }

  /**
   * Log de error. Equivalente a `console.error`.
   * Nunca incluir passwords, tokens ni datos personales en `data`.
   * @param context  Nombre del componente o servicio que emite el log.
   * @param message  Mensaje descriptivo.
   * @param data     Datos opcionales (sanitizados).
   */
  error(context: string, message: string, ...data: any[]): void {
    if (!this.isEnabled) return;
    console.error(`%c[${context}]`, 'color: #EA5455; font-weight: bold;', message, ...data);
  }

  /**
   * Abre un grupo colapsable en la consola.
   * Útil para agrupar logs relacionados (ej. ciclo de vida de un componente).
   * @param label  Título del grupo.
   */
  group(label: string): void {
    if (!this.isEnabled) return;
    console.group(`%c[DEBUG] ${label}`, 'color: #7367F0; font-weight: bold;');
  }

  /** Cierra el grupo abierto con `group()`. */
  groupEnd(): void {
    if (!this.isEnabled) return;
    console.groupEnd();
  }

  /**
   * Mide el tiempo de ejecución de un bloque de código.
   * @param label  Etiqueta del timer (debe ser única).
   */
  time(label: string): void {
    if (!this.isEnabled) return;
    console.time(`[DEBUG] ${label}`);
  }

  /** Finaliza el timer iniciado con `time()` e imprime el resultado. */
  timeEnd(label: string): void {
    if (!this.isEnabled) return;
    console.timeEnd(`[DEBUG] ${label}`);
  }
}
