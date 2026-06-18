# Guía Frontend — Re-descarga de Certificado Viafirma (Admin)

> **Fecha de creación:** 2026-06-18 16:42 (hora local COT)
> **Autor:** Arquitectura / Backend
> **Destinatario:** Equipo Frontend (Angular)
> **Estado:** 📋 **Pendiente de implementación en UI**
> **Ámbito:** Panel de administración — módulo de solicitudes de certificados
> **Relacionado:** `docs/2026-06-06-10-55-guia-frontend-viafirma-pj.md`

---

## 1. Resumen de la Feature

Se agrega un nuevo endpoint exclusivo para administradores que permite **re-descargar el archivo P7B desde Viafirma y regenerar el P12** con un nuevo PIN, en casos donde el certificado se haya perdido, corrompido o el PIN haya expirado.

**Endpoint nuevo:**
```
POST /api/v1/certificate-request/{id}/issuance/redownload
```

---

## 2. Contexto: Flujo Completo de Viafirma

Para entender cuándo aparece el botón de re-descarga, es importante conocer el flujo completo:

```
[Solicitud creada]
      ↓
[PROCESSING] → POST /issue → [SUBMITTED] (Viafirma recibe el CSR)
      ↓
[POLLING] → El sistema consulta el estado cada 60 segundos
      ↓
[READY_TO_DOWNLOAD] → El sistema descarga el P7B automáticamente
      ↓
[ASSEMBLED] → El sistema genera el P12 con PIN
      ↓
[COMPLETED] → El cliente puede descargar el P12
```

**Estados internos de Viafirma (`internal_state`):**

| Valor | Descripción | Acción del usuario |
|---|---|---|
| `draft` | Borrador | — |
| `submitted` | Enviado a Viafirma | Esperar |
| `polling` | Consultando estado | Esperar |
| `ready_to_download` | Listo para descarga | Esperar (automático) |
| `downloaded` | P7B descargado | Esperar (automático) |
| `assembled` | P12 generado | **Puede descargar** |
| `completed` | Completado | **Puede descargar** |
| `failed` | Fallido | Reintentar emisión |
| `failed_recoverable` | Fallo recuperable | Contactar soporte |
| `expired` | Expirado (SLA 72h) | Nueva emisión |

---

## 3. Nuevos Componentes UI Requeridos

### 3.1 Botón "Re-descargar Certificado" (Solo Admin)

**Dónde:** En la vista de detalle de una solicitud de certificado, dentro de la sección de estado Viafirma.

**Condiciones para mostrar el botón:**
- El usuario autenticado tiene rol `admin` o `is_admin = true`
- El `internal_state` es `assembled`, `completed`, `failed`, o `downloaded`
- El proveedor de emisión es `viafirma`

**Condiciones para habilitar el botón (no disabled):**
- El `internal_state` es `assembled`, `completed`, o `downloaded`

```html
<!-- Ejemplo Angular -->
<button
  *ngIf="isAdmin && isViafirmaProvider"
  [disabled]="!canRedownload || isRedownloading"
  (click)="onRedownload()"
  class="btn btn-warning">
  <mat-icon>refresh</mat-icon>
  {{ isRedownloading ? 'Re-descargando...' : 'Re-descargar Certificado' }}
</button>
```

```typescript
get canRedownload(): boolean {
  const allowedStates = ['assembled', 'completed', 'downloaded'];
  return allowedStates.includes(this.viafirmaStatus?.internal_state);
}
```

---

### 3.2 Modal de Confirmación

Antes de ejecutar la re-descarga, mostrar un modal de confirmación:

```
┌─────────────────────────────────────────────────────┐
│  ⚠️  Re-descargar Certificado                        │
│                                                     │
│  Esta acción:                                       │
│  • Consultará el estado actual en Viafirma          │
│  • Descargará nuevamente el archivo del certificado │
│  • Generará un NUEVO PIN de acceso                  │
│                                                     │
│  ⚠️ El PIN anterior quedará inválido.               │
│                                                     │
│  ¿Desea continuar?                                  │
│                                                     │
│  [Cancelar]              [Confirmar Re-descarga]    │
└─────────────────────────────────────────────────────┘
```

---

### 3.3 Modal de Resultado (PIN)

Después de una re-descarga exitosa, mostrar el nuevo PIN en un modal:

```
┌─────────────────────────────────────────────────────┐
│  ✅  Certificado Re-descargado Exitosamente          │
│                                                     │
│  Nuevo PIN de acceso:                               │
│  ┌─────────────────────────────────────────────┐   │
│  │  aB3xK9mNpQ7rT2vW8zC5dE1fG4hJ6kL0mN9oP    │   │
│  └─────────────────────────────────────────────┘   │
│  [📋 Copiar PIN]                                    │
│                                                     │
│  ⚠️ Guarde este PIN. No se mostrará nuevamente.    │
│                                                     │
│  [Descargar P12]              [Cerrar]              │
└─────────────────────────────────────────────────────┘
```

**Importante:** El PIN solo se muestra una vez. El modal no debe cerrarse accidentalmente.

---

## 4. Integración con la API

### 4.1 Llamada al Endpoint de Re-descarga

```typescript
// viafirma.service.ts

redownloadCertificate(certificateRequestId: number): Observable<RedownloadResult> {
  return this.http.post<ApiResponse<RedownloadResult>>(
    `${this.apiUrl}/certificate-request/${certificateRequestId}/issuance/redownload`,
    {}
  ).pipe(
    map(response => response.dataRecords),
    catchError(this.handleRedownloadError)
  );
}

private handleRedownloadError(error: HttpErrorResponse): Observable<never> {
  switch (error.status) {
    case 403:
      return throwError(() => new Error('No tiene permisos para esta operación.'));
    case 404:
      return throwError(() => new Error('No se encontró el trámite Viafirma.'));
    case 409:
      return throwError(() => new Error(
        `El certificado aún no está listo para descarga. Estado actual: ${error.error?.remote_status}`
      ));
    case 422:
      return throwError(() => new Error(
        'La llave privada fue purgada. Se requiere una nueva emisión del certificado.'
      ));
    case 502:
      return throwError(() => new Error(
        'Error al comunicarse con Viafirma. Intente nuevamente en unos minutos.'
      ));
    default:
      return throwError(() => new Error('Error inesperado. Contacte al soporte técnico.'));
  }
}
```

### 4.2 Interfaces TypeScript

```typescript
// interfaces/viafirma.interface.ts

export interface RedownloadResult {
  pin: string;
  download_url: string;
  expires_at: string | null;
  viafirma_id: number;
  internal_state: ViafirmaInternalState;
  remote_status: string;
}

export type ViafirmaInternalState =
  | 'draft'
  | 'submitted'
  | 'polling'
  | 'ready_to_download'
  | 'downloaded'
  | 'assembled'
  | 'completed'
  | 'failed'
  | 'failed_recoverable'
  | 'expired';

export interface ViafirmaStatus {
  internal_state: ViafirmaInternalState;
  remote_status: string;
  public_id: string | null;
  cod_request: string | null;
  submitted_at: string | null;
  assembled_at: string | null;
  expires_at: string | null;
  poll_attempts: number;
  last_error_code: string | null;
  last_error_message: string | null;
}
```

### 4.3 Flujo Completo en el Componente

```typescript
// certificate-detail.component.ts

isRedownloading = false;
redownloadResult: RedownloadResult | null = null;

async onRedownload(): Promise<void> {
  // 1. Mostrar modal de confirmación
  const confirmed = await this.confirmDialog.open({
    title: 'Re-descargar Certificado',
    message: 'Esta acción generará un nuevo PIN. El PIN anterior quedará inválido.',
    confirmText: 'Confirmar Re-descarga',
    cancelText: 'Cancelar',
    type: 'warning'
  });

  if (!confirmed) return;

  // 2. Ejecutar re-descarga
  this.isRedownloading = true;

  this.viafirmaService.redownloadCertificate(this.certificateRequestId)
    .pipe(finalize(() => this.isRedownloading = false))
    .subscribe({
      next: (result) => {
        this.redownloadResult = result;
        // 3. Mostrar modal con el nuevo PIN
        this.showPinModal(result);
        // 4. Actualizar estado en la vista
        this.loadViafirmaStatus();
      },
      error: (err) => {
        this.notificationService.error(err.message);
      }
    });
}

showPinModal(result: RedownloadResult): void {
  this.dialog.open(PinDisplayModalComponent, {
    data: result,
    disableClose: true, // No cerrar accidentalmente
    width: '500px'
  });
}
```

---

## 5. Endpoints Disponibles (Referencia Completa)

### 5.1 Endpoints Existentes

| Método | Endpoint | Descripción | Quién puede usar |
|---|---|---|---|
| `POST` | `/certificate-request/{id}/issue` | Iniciar emisión | Admin + Usuario |
| `GET` | `/certificate-request/{id}/issuance` | Consultar estado | Admin + Usuario |
| `GET` | `/certificate-request/{id}/issuance/download` | Obtener PIN + URL | Admin + Usuario |
| `GET` | `/certificate-request/{id}/issuance/download/file` | Descargar P12 binario | Admin + Usuario |
| `POST` | `/certificate-request/{id}/revoke` | Revocar certificado | Admin |
| `GET` | `/certificate-request/{id}/kyc-link` | Obtener link KYC | Admin + Usuario |

### 5.2 Endpoint Nuevo

| Método | Endpoint | Descripción | Quién puede usar |
|---|---|---|---|
| `POST` | `/certificate-request/{id}/issuance/redownload` | Re-descargar y regenerar P12 | **Solo Admin** |

---

## 6. Manejo de Estados en la UI

### 6.1 Tabla de Estados y Acciones Disponibles

| `internal_state` | Descripción UI | Botón Descargar | Botón Re-descargar (Admin) |
|---|---|---|---|
| `submitted` | ⏳ Enviado a Viafirma | ❌ | ❌ |
| `polling` | 🔄 Procesando... | ❌ | ❌ |
| `ready_to_download` | 🔄 Descargando... | ❌ | ❌ |
| `downloaded` | 🔄 Generando P12... | ❌ | ✅ (habilitado) |
| `assembled` | ✅ Listo para descargar | ✅ | ✅ (habilitado) |
| `completed` | ✅ Completado | ✅ | ✅ (habilitado) |
| `failed` | ❌ Falló | ❌ | ✅ (deshabilitado — llave puede estar purgada) |
| `failed_recoverable` | ⚠️ Requiere intervención | ❌ | ❌ |
| `expired` | ⏰ Expirado | ❌ | ❌ |

### 6.2 Indicador de Progreso (Polling)

Mientras el estado es `submitted` o `polling`, mostrar un indicador de progreso con el número de intentos:

```html
<div *ngIf="isPolling" class="polling-indicator">
  <mat-progress-bar mode="indeterminate"></mat-progress-bar>
  <p>Procesando solicitud... (intento {{ viafirmaStatus?.poll_attempts }} de 288)</p>
  <p class="text-muted">El sistema consulta el estado cada 60 segundos automáticamente.</p>
</div>
```

---

## 7. Polling desde el Frontend (Opcional)

El backend hace el polling automáticamente. Sin embargo, para actualizar la UI en tiempo real, el frontend puede hacer polling al endpoint de estado:

```typescript
// Polling cada 30 segundos mientras el estado no sea terminal
startPolling(certificateRequestId: number): void {
  const terminalStates: ViafirmaInternalState[] = [
    'assembled', 'completed', 'failed', 'failed_recoverable', 'expired'
  ];

  this.pollingSubscription = interval(30000)
    .pipe(
      switchMap(() => this.viafirmaService.getStatus(certificateRequestId)),
      takeUntil(this.destroy$)
    )
    .subscribe(status => {
      this.viafirmaStatus = status;
      if (terminalStates.includes(status.internal_state)) {
        this.stopPolling();
      }
    });
}
```

**Endpoint para consultar estado:**
```
GET /api/v1/certificate-request/{id}/issuance
```

---

## 8. Mensajes de Error para el Usuario

| Código HTTP | Mensaje para el usuario |
|---|---|
| `403` | "No tiene permisos para realizar esta operación." |
| `404` | "No se encontró el trámite de certificado." |
| `409` | "El certificado aún no está disponible para descarga. Estado actual: {remote_status}" |
| `422` | "La llave privada fue purgada. Contacte al soporte para una nueva emisión." |
| `502` | "Error de comunicación con Viafirma. Intente nuevamente en unos minutos." |
| `500` | "Error interno del servidor. Contacte al soporte técnico." |

---

## 9. Checklist de Implementación Frontend

- [ ] Agregar botón "Re-descargar Certificado" en vista de detalle (solo admin)
- [ ] Implementar modal de confirmación antes de ejecutar
- [ ] Implementar modal de resultado con PIN (no cerrable accidentalmente)
- [ ] Agregar botón "Copiar PIN" en el modal de resultado
- [ ] Implementar `redownloadCertificate()` en `ViafirmaService`
- [ ] Agregar interfaces `RedownloadResult` y `ViafirmaStatus`
- [ ] Manejar todos los códigos de error (403, 404, 409, 422, 502)
- [ ] Actualizar tabla de estados con el nuevo botón
- [ ] Agregar indicador de progreso durante el polling
- [ ] Implementar polling opcional desde el frontend (30s)
- [ ] Agregar tooltip explicativo en el botón de re-descarga

---

## 10. Notas Importantes

> ⚠️ **El PIN solo se muestra una vez.** El modal de resultado no debe cerrarse hasta que el admin confirme que copió el PIN.

> ⚠️ **El PIN anterior queda inválido** después de una re-descarga. Si el cliente ya tenía el PIN anterior, debe usar el nuevo.

> ℹ️ **El archivo P12 es el mismo.** Solo cambia el PIN de acceso. El certificado digital en sí no cambia.

> ℹ️ **La URL de descarga no cambia.** Sigue siendo `GET /issuance/download/file`.

> ℹ️ **Solo funciona si el estado remoto en Viafirma es `Generated_Not_Downloaded` o `Generated_And_Downloaded`.** Si el estado es otro (ej: `accreditation`), el backend retornará 409.
