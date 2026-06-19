# Feature: Re-descarga y Regeneración de P12 — Endpoint Admin Viafirma

> **Fecha de creación:** 2026-06-18 16:42 (hora local COT)
> **Autor:** Arquitectura / Backend
> **Estado:** 📋 **Pendiente de implementación**
> **Ámbito:** `backend/` (Laravel) — módulo `app/Modules/Viafirma/` + `app/Http/Controllers/Certificate/`
> **Tickets relacionados:** Viafirma RA — Flujo de re-descarga admin
> **Principios rectores:** SOLID (SRP, OCP), Clean Architecture, Security by Design

---

## 1. Contexto y Problema

### 1.1 Situación Actual

El flujo automático de Viafirma descarga el P7B y ensambla el P12 una sola vez, de forma asíncrona, cuando el estado remoto llega a `Generated_Not_Downloaded`. Una vez completado, el estado interno pasa a `COMPLETED`.

**Problema:** Si el archivo P12 se corrompe, se pierde del storage, o el PIN del vault es purgado antes de que el cliente lo descargue, **no existe un mecanismo para que el ADMIN regenere el certificado** sin intervención manual en la base de datos.

### 1.2 Requerimiento

Crear un endpoint exclusivo para ADMIN que:

1. Consulte el estado remoto en Viafirma (`GET /request/{codRequest}/status`)
2. Valide que el estado sea `Generated_Not_Downloaded` o `Generated_And_Downloaded`
3. Descargue nuevamente el P7B desde Viafirma (`GET /downloadCertificateServlet?req={publicId}`)
4. Regenere el P12 con un nuevo PIN
5. Actualice el registro en BD
6. Retorne el nuevo PIN al ADMIN

---

## 2. Objetivos

1. **Resiliencia operativa:** Permitir recuperación sin intervención manual en BD.
2. **Seguridad:** Solo ADMIN puede ejecutar esta operación.
3. **Validación remota:** Siempre consultar Viafirma antes de proceder (no confiar solo en el estado interno).
4. **Auditoría completa:** Registrar en `viafirma_status_history` y `change_histories`.
5. **Idempotencia:** Si el P12 ya existe, sobrescribirlo de forma segura.

---

## 3. Diseño Arquitectónico

### 3.1 Flujo de Ejecución

```
POST /api/v1/certificate-request/{id}/issuance/redownload
    │
    ├─ [Auth] Requiere auth:api
    ├─ [Authz] Requiere is_admin = true
    │
    ▼
CertificateIssuanceController::redownload()
    │
    ▼
RedownloadCertificateUseCase::handle(int $certificateRequestId, int $adminUserId)
    │
    ├─ 1. Buscar ViafirmaCertificateRequest por certificate_request_id
    │       └─ 404 si no existe
    │
    ├─ 2. Consultar estado remoto
    │       GET {VIAFIRMA_RA_URL}/request/{codRequest}/status
    │       └─ 502 si falla la consulta HTTP
    │
    ├─ 3. Validar estado remoto
    │       ¿code == "Generated_Not_Downloaded" || "Generated_And_Downloaded"?
    │       └─ 409 si el estado no permite re-descarga
    │
    ├─ 4. Descargar P7B
    │       GET {VIAFIRMA_RA_DOWNLOAD_URL}/downloadCertificateServlet?req={publicId}
    │       └─ 502 si falla la descarga
    │
    ├─ 5. Guardar P7B en storage (sobrescribir)
    │
    ├─ 6. Generar nuevo PIN CSPRNG (32 chars)
    │
    ├─ 7. Recuperar llave privada del KeyVault (key_vault_ref)
    │       └─ 422 si la llave fue purgada (key_vault_ref == 'PURGED')
    │
    ├─ 8. Ensamblar nuevo P12
    │       CryptoService::assembleP12(privateKeyPem, p7bBinary, pin)
    │
    ├─ 9. Guardar P12 en storage (sobrescribir)
    │
    ├─ 10. Destruir PIN anterior del vault (si existe y no es PURGED)
    │
    ├─ 11. Guardar nuevo PIN en KeyVault
    │
    ├─ 12. Actualizar ViafirmaCertificateRequest:
    │        - p12_storage_path (actualizar si cambió)
    │        - p12_password_ref (nueva referencia)
    │        - internal_state = ASSEMBLED
    │        - last_error_code = null
    │        - last_error_message = null
    │
    ├─ 13. Registrar en viafirma_status_history
    │
    ├─ 14. Registrar en change_histories
    │
    └─ 15. Retornar RedownloadResultDto { pin, download_url, expires_at }
```

### 3.2 Componentes Nuevos

| Componente | Tipo | Ubicación |
|---|---|---|
| `RedownloadCertificateUseCase` | UseCase | `app/Modules/Viafirma/Application/UseCases/` |
| `RedownloadResultDto` | DTO | `app/Modules/Viafirma/Application/DTOs/` |
| `AutoRedownloadPendingViafirmaJob` | Job (Watchdog) | `app/Modules/Viafirma/Infrastructure/Jobs/` |

### 3.3 Componentes Modificados

| Componente | Cambio |
|---|---|
| `CertificateIssuanceController` | Agregar método `redownload()` |
| `routes/api.php` | Agregar ruta `POST /{id}/issuance/redownload` |
| `ViafirmaCertificateRequest` | Agregar scope `pendingAutoRedownload()` |
| `app/Console/Kernel.php` | Registrar `AutoRedownloadPendingViafirmaJob` en scheduler (cada 5 min) |

---

## 4. Especificación del Endpoint

### 4.1 Request

```
POST /api/v1/certificate-request/{id}/issuance/redownload
Authorization: Bearer {token}
Content-Type: application/json
```

**Parámetros de ruta:**
| Parámetro | Tipo | Descripción |
|---|---|---|
| `id` | integer | ID de `certificate_requests.id` |

**Body:** No requerido.

### 4.2 Respuestas

#### 200 OK — Re-descarga exitosa
```json
{
  "success": true,
  "message": "Certificado re-descargado y P12 regenerado exitosamente.",
  "dataRecords": {
    "pin": "aB3xK9mNpQ7rT2vW",
    "download_url": "/api/v1/certificate-request/123/issuance/download/file",
    "expires_at": null,
    "viafirma_id": 45,
    "internal_state": "assembled",
    "remote_status": "Generated_And_Downloaded"
  }
}
```

#### 403 Forbidden — No es ADMIN
```json
{
  "success": false,
  "message": "No autorizado. Esta operación requiere permisos de administrador."
}
```

#### 404 Not Found — Solicitud o trámite no encontrado
```json
{
  "success": false,
  "message": "No se encontró un trámite Viafirma para la solicitud 123."
}
```

#### 409 Conflict — Estado remoto no permite re-descarga
```json
{
  "success": false,
  "message": "El estado remoto 'accreditation' no permite re-descarga. Solo se permite en estados: Generated_Not_Downloaded, Generated_And_Downloaded.",
  "remote_status": "accreditation"
}
```

#### 422 Unprocessable Entity — Llave privada purgada
```json
{
  "success": false,
  "message": "La llave privada de esta solicitud fue purgada y no puede regenerarse el P12. Se requiere una nueva emisión."
}
```

#### 502 Bad Gateway — Error al consultar Viafirma
```json
{
  "success": false,
  "message": "Error al consultar el estado remoto en Viafirma: Connection timeout."
}
```

---

## 5. Implementación

### 5.1 RedownloadResultDto

```php
// app/Modules/Viafirma/Application/DTOs/RedownloadResultDto.php

final class RedownloadResultDto
{
    public function __construct(
        public readonly string $pin,
        public readonly string $downloadUrl,
        public readonly int    $viafirmaId,
        public readonly string $internalState,
        public readonly string $remoteStatus,
    ) {}

    public function toArray(): array
    {
        return [
            'pin'            => $this->pin,
            'download_url'   => $this->downloadUrl,
            'expires_at'     => null,
            'viafirma_id'    => $this->viafirmaId,
            'internal_state' => $this->internalState,
            'remote_status'  => $this->remoteStatus,
        ];
    }
}
```

### 5.2 RedownloadCertificateUseCase (esqueleto)

```php
// app/Modules/Viafirma/Application/UseCases/RedownloadCertificateUseCase.php

final class RedownloadCertificateUseCase
{
    public function __construct(
        private readonly ViafirmaClient $client,
        private readonly CryptoServiceContract $crypto,
        private readonly KeyVault $vault,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(int $certificateRequestId, int $adminUserId): RedownloadResultDto
    {
        // 1. Buscar entidad
        $entity = ViafirmaCertificateRequest::where('certificate_request_id', $certificateRequestId)
            ->firstOrFail();

        // 2. Consultar estado remoto
        $statusResult = $this->client->getStatus($entity->cod_request);

        // 3. Validar estado
        if (!$statusResult->status->isReadyToDownload() && !$statusResult->status->isTerminalOk()) {
            throw new ViafirmaException(
                "El estado remoto '{$statusResult->status->value}' no permite re-descarga. " .
                "Solo se permite en estados: Generated_Not_Downloaded, Generated_And_Downloaded.",
                409
            );
        }

        // 4. Validar que la llave privada no fue purgada
        if ($entity->key_vault_ref === 'PURGED' || empty($entity->key_vault_ref)) {
            throw new ViafirmaException(
                'La llave privada de esta solicitud fue purgada y no puede regenerarse el P12.',
                422
            );
        }

        // 5-11. Descargar + ensamblar (ver implementación completa)
        // ...

        return new RedownloadResultDto(
            pin:           $newPin,
            downloadUrl:   route('v1.certificate-request.issuance.download.file', ['id' => $certificateRequestId]),
            viafirmaId:    $entity->id,
            internalState: InternalState::ASSEMBLED->value,
            remoteStatus:  $statusResult->status->value,
        );
    }
}
```

### 5.3 Ruta a agregar en routes/api.php

```php
// Dentro del grupo certificate-request, junto a los otros endpoints de issuance:
Route::post('/{id}/issuance/redownload', 'redownload')
    ->name('v1.certificate-request.issuance.redownload');
```

---

### 5.4 Job Automático: AutoRedownloadPendingViafirmaJob

#### 5.4.1 Problema Real (Caso de Uso)

Durante la operación normal del sistema se puede presentar el siguiente escenario:

1. `AssembleP12Job` descarga el P7B y ensambla el P12 exitosamente en Viafirma (estado remoto: `Generated_And_Downloaded`).
2. Sin embargo, **un error transitorio** (timeout de red, fallo de S3, excepción en el vault) ocurre **después** de la descarga pero **antes** de que se actualice el estado interno en BD.
3. El job falla y Laravel marca el intento como `FAILED`. Tras agotar los reintentos, el estado interno queda en `FAILED_RECOVERABLE`.
4. **Resultado:** El certificado existe y está disponible en Viafirma (`Generated_And_Downloaded`), pero la BD lo reporta como fallido. El cliente no puede descargarlo.

Este job actúa como **watchdog automático** que detecta y corrige esta inconsistencia sin intervención manual.

---

#### 5.4.2 Flujo de Ejecución

```
[Scheduler] Cada 5 minutos
    │
    ▼
AutoRedownloadPendingViafirmaJob::handle()
    │
    ├─ 1. Buscar candidatos con scope pendingAutoRedownload()
    │       ViafirmaCertificateRequest::pendingAutoRedownload()->get()
    │       └─ Si no hay candidatos → Log::info + return (sin coste)
    │
    ├─ 2. Para cada candidato:
    │       │
    │       ├─ 2a. Consultar estado remoto en Viafirma
    │       │       GET {VIAFIRMA_RA_URL}/request/{codRequest}/status
    │       │       └─ Si falla → Log::warning + continuar con el siguiente
    │       │
    │       ├─ 2b. Validar estado remoto
    │       │       ¿code == "Generated_And_Downloaded" || "Generated_Not_Downloaded"?
    │       │       └─ Si no → Log::info('estado no apto') + continuar
    │       │
    │       ├─ 2c. Validar que key_vault_ref no sea 'PURGED'
    │       │       └─ Si purgada → marcar FAILED_PERMANENT + continuar
    │       │
    │       ├─ 2d. Despachar RedownloadCertificateUseCase (reutiliza la lógica del endpoint admin)
    │       │       con delay aleatorio (5-30s) para evitar thundering herd
    │       │
    │       └─ 2e. Log::info('viafirma.auto_redownload.dispatched', [cr_id, viafirma_id])
    │
    └─ 3. Log::info con total de candidatos procesados
```

---

#### 5.4.3 Scope `pendingAutoRedownload()` en `ViafirmaCertificateRequest`

El scope filtra los registros que son candidatos para re-descarga automática:

```php
// app/Modules/Viafirma/Infrastructure/Persistence/Models/ViafirmaCertificateRequest.php

/**
 * Candidatos para re-descarga automática:
 * - Estado interno FAILED_RECOVERABLE (el job de ensamblado falló pero es recuperable)
 * - La llave privada NO fue purgada (key_vault_ref != 'PURGED' y no es null)
 * - Llevan al menos 2 minutos en ese estado (evitar colisión con reintentos activos)
 * - No han superado el máximo de intentos de re-descarga automática (max: 5)
 */
public function scopePendingAutoRedownload(Builder $query): Builder
{
    return $query
        ->where('internal_state', InternalState::FAILED_RECOVERABLE->value)
        ->where('key_vault_ref', '!=', 'PURGED')
        ->whereNotNull('key_vault_ref')
        ->where('updated_at', '<', now()->subMinutes(2))
        ->where(function (Builder $q) {
            $q->whereNull('auto_redownload_attempts')
              ->orWhere('auto_redownload_attempts', '<', 5);
        });
}
```

**Criterios de exclusión:**
- `internal_state != FAILED_RECOVERABLE` → No es candidato (ya está en COMPLETED, POLLING, etc.)
- `key_vault_ref == 'PURGED'` → La llave fue destruida; requiere nueva emisión completa
- `key_vault_ref IS NULL` → Sin referencia de vault; no se puede ensamblar el P12
- `updated_at >= now() - 2min` → Gracia para evitar colisión con `AssembleP12Job` en curso
- `auto_redownload_attempts >= 5` → Máximo de intentos automáticos alcanzado; requiere intervención manual

---

#### 5.4.4 Implementación del Job

```php
// app/Modules/Viafirma/Infrastructure/Jobs/AutoRedownloadPendingViafirmaJob.php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Application\UseCases\RedownloadCertificateUseCase;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Watchdog automático que detecta certificados Viafirma en estado FAILED_RECOVERABLE
 * cuyo estado remoto ya es Generated_And_Downloaded, y los re-descarga automáticamente.
 *
 * Ejecutado por el scheduler cada 5 minutos.
 */
final class AutoRedownloadPendingViafirmaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Intentos del watchdog en sí (no de cada re-descarga individual). */
    public int $tries = 1;

    /** Timeout del watchdog: suficiente para consultar BD + despachar jobs. */
    public int $timeout = 60;

    public function handle(RedownloadCertificateUseCase $useCase): void
    {
        $candidates = ViafirmaCertificateRequest::pendingAutoRedownload()->get();

        if ($candidates->isEmpty()) {
            Log::info('viafirma.auto_redownload.no_candidates');
            return;
        }

        Log::info('viafirma.auto_redownload.candidates_found', [
            'count' => $candidates->count(),
        ]);

        $dispatched = 0;

        foreach ($candidates as $entity) {
            try {
                // Incrementar contador de intentos automáticos antes de despachar
                $entity->increment('auto_redownload_attempts');

                // Despachar con delay aleatorio para evitar thundering herd
                dispatch(function () use ($useCase, $entity) {
                    $useCase->handle(
                        certificateRequestId: $entity->certificate_request_id,
                        adminUserId:          null, // Sistema — no hay usuario admin
                    );
                })->delay(now()->addSeconds(random_int(5, 30)));

                $dispatched++;

                Log::info('viafirma.auto_redownload.dispatched', [
                    'viafirma_id' => $entity->id,
                    'cr_id'       => $entity->certificate_request_id,
                    'attempt'     => $entity->auto_redownload_attempts,
                ]);

            } catch (Throwable $e) {
                // NO relanzar — continuar con el siguiente candidato
                Log::error('viafirma.auto_redownload.dispatch_error', [
                    'viafirma_id' => $entity->id,
                    'cr_id'       => $entity->certificate_request_id,
                    'error'       => $e->getMessage(),
                    'class'       => get_class($e),
                ]);
            }
        }

        Log::info('viafirma.auto_redownload.completed', [
            'total_candidates' => $candidates->count(),
            'dispatched'       => $dispatched,
        ]);
    }
}
```

---

#### 5.4.5 Configuración en Kernel.php

```php
// app/Console/Kernel.php — dentro de schedule()

// ====================================================================
// VIAFIRMA AUTO RE-DESCARGA
// ====================================================================

/**
 * Job 9: Re-descarga automática de certificados Viafirma en FAILED_RECOVERABLE
 *
 * Frecuencia: Cada 5 minutos
 * Función: Detecta certificados cuyo estado remoto es Generated_And_Downloaded
 *          pero el estado interno es FAILED_RECOVERABLE, y los re-descarga
 *          automáticamente reutilizando RedownloadCertificateUseCase.
 * Queue: default
 */
$schedule->job(new \App\Modules\Viafirma\Infrastructure\Jobs\AutoRedownloadPendingViafirmaJob())
    ->everyFiveMinutes()
    ->timezone('America/Bogota')
    ->name('viafirma:auto-redownload-pending')
    ->withoutOverlapping(10)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/scheduled-viafirma-auto-redownload.log'));
```

---

#### 5.4.6 Manejo de Errores y Reintentos

| Escenario | Comportamiento |
|---|---|
| **Viafirma no responde** | `Log::warning` + continuar con el siguiente candidato. El watchdog no falla. |
| **Estado remoto no apto** | `Log::info` + continuar. El candidato permanece en `FAILED_RECOVERABLE` para el próximo ciclo. |
| **Llave privada purgada** | Marcar `internal_state = FAILED_PERMANENT` + `Log::error`. No reintentar. |
| **Error en `RedownloadCertificateUseCase`** | `Log::error` + continuar. El contador `auto_redownload_attempts` ya fue incrementado. |
| **`auto_redownload_attempts >= 5`** | El scope excluye el candidato. Requiere intervención manual del ADMIN vía endpoint. |
| **Circuit Breaker OPEN** | `ViafirmaCircuitBreaker` lanza excepción → capturada por el try/catch del loop. |

**Relación con el endpoint admin:**
- El job **reutiliza `RedownloadCertificateUseCase`** — misma lógica, sin duplicación.
- El endpoint admin permite recuperar casos donde `auto_redownload_attempts >= 5`.
- El job actúa como **primera línea de recuperación automática**; el endpoint como **segunda línea manual**.

---

## 6. Seguridad

| Aspecto | Implementación |
|---|---|
| **Autenticación** | `auth:api` middleware |
| **Autorización** | `callerIsAdmin()` — retorna 403 si no es admin |
| **Validación remota** | Siempre consulta Viafirma antes de proceder |
| **Protección de PIN** | PIN nunca se loguea (SafePemLogger redacta `pin` y `p12_password_ref`) |
| **Limpieza de secretos** | PIN anterior destruido del vault antes de guardar el nuevo |
| **Auditoría** | Registra en `viafirma_status_history` con `action: admin_redownload` |
| **Rate limiting** | Considerar agregar `throttle:viafirma-redownload` (5 req/min por admin) |

---

## 7. Consideraciones de Negocio

### 7.1 Cuándo usar este endpoint

✅ El archivo P12 se corrompió o se perdió del storage
✅ El PIN fue purgado antes de que el cliente lo descargara
✅ El cliente reporta que no pudo descargar el certificado
✅ Recuperación manual tras un fallo en `AssembleP12Job`

### 7.2 Cuándo NO usar este endpoint

❌ El estado remoto es `accreditation`, `inProcess`, etc. (el certificado aún no está generado)
❌ La llave privada fue purgada (requiere nueva emisión completa)
❌ El trámite está en estado `FAILED` o `EXPIRED` (requiere nueva emisión)

### 7.3 Impacto en el cliente

- El **PIN cambia** con cada re-descarga — el cliente debe usar el nuevo PIN
- El **archivo P12 es el mismo** (mismo certificado, misma llave privada)
- La **URL de descarga no cambia** — sigue siendo `GET /issuance/download/file`

---

## 8. Testing

### 8.1 Casos de prueba unitarios

```
RedownloadCertificateUseCaseTest:
  ✅ handle_success_when_status_is_generated_not_downloaded
  ✅ handle_success_when_status_is_generated_and_downloaded
  ✅ throws_409_when_remote_status_is_not_downloadable
  ✅ throws_422_when_key_vault_ref_is_purged
  ✅ throws_404_when_viafirma_request_not_found
  ✅ throws_502_when_viafirma_client_fails
  ✅ destroys_old_pin_ref_before_storing_new_one
  ✅ records_history_on_success
```

### 8.2 Casos de prueba de integración

```
RedownloadControllerTest:
  ✅ returns_403_when_caller_is_not_admin
  ✅ returns_200_with_pin_on_success
  ✅ returns_409_when_remote_status_invalid
  ✅ returns_404_when_certificate_request_not_found
```

---

## 9. Checklist de Implementación

### 9.1 Endpoint Admin (Re-descarga Manual)
- [ ] Crear `RedownloadResultDto`
- [ ] Crear `RedownloadCertificateUseCase`
- [ ] Registrar UseCase en `ViafirmaServiceProvider`
- [ ] Agregar método `redownload()` en `CertificateIssuanceController`
- [ ] Agregar ruta en `routes/api.php`
- [ ] Agregar throttle rate limiter en `RouteServiceProvider` (opcional)
- [ ] Escribir tests unitarios (`RedownloadCertificateUseCaseTest`)
- [ ] Escribir tests de integración (`RedownloadControllerTest`)
- [ ] Actualizar documentación OpenAPI (anotaciones `@OA`)

### 9.2 Job Automático (Re-descarga Watchdog)
- [ ] Agregar columna `auto_redownload_attempts` (integer, nullable, default 0) en migración de `viafirma_certificate_requests`
- [ ] Agregar scope `pendingAutoRedownload()` en `ViafirmaCertificateRequest`
- [ ] Crear `AutoRedownloadPendingViafirmaJob` en `app/Modules/Viafirma/Infrastructure/Jobs/`
- [ ] Registrar `AutoRedownloadPendingViafirmaJob` en `Kernel.php` (scheduler cada 5 min)
- [ ] Escribir tests unitarios (`AutoRedownloadPendingViafirmaJobTest`)
- [ ] Verificar que `RedownloadCertificateUseCase` acepta `adminUserId = null` (invocación desde sistema)

---

## 10. Commit Sugerido

```
feat(viafirma): endpoint admin + job watchdog para re-descarga y regeneración de P12

- POST /api/v1/certificate-request/{id}/issuance/redownload (endpoint admin)
  · Valida estado remoto antes de proceder (Generated_Not_Downloaded | Generated_And_Downloaded)
  · Regenera P12 con nuevo PIN CSPRNG
  · Destruye PIN anterior del vault
  · Registra auditoría en viafirma_status_history
  · Solo accesible para usuarios con rol admin

- AutoRedownloadPendingViafirmaJob (watchdog automático cada 5 min)
  · Detecta certificados en FAILED_RECOVERABLE con estado remoto Generated_And_Downloaded
  · Reutiliza RedownloadCertificateUseCase (sin duplicación de lógica)
  · Máximo 5 intentos automáticos; escala a intervención manual vía endpoint admin
  · Scope pendingAutoRedownload() en ViafirmaCertificateRequest
  · Columna auto_redownload_attempts en viafirma_certificate_requests
```
