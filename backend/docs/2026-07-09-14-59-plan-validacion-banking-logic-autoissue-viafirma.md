# Plan: Validación "banking logic" en creación de CertificateRequest (flujo Viafirma)

**Fecha:** 2026-07-09
**Alcance:** `AutoIssueViafirmaJob` y el flujo de creación de `CertificateRequest` (viafirma).
**Estado:** ✅ IMPLEMENTADO — todas las fases completadas.

---

## Contexto

`AutoIssueViafirmaJob` (líneas 49, 57, 65, 135) contiene lógica que, ante datos estructuralmente inválidos o ausentes, **registra un warning y sigue adelante silenciosamente** (retorna `null`/`return` sin excepción) en vez de fallar. Esto permite que una `CertificateRequest` con datos incompletos:

1. Se cree sin validación completa (`entity_document_type_id` se defaultea a `1` en `CertificateRequestService.php:46` si el cliente no lo envía).
2. Llegue al job, que intenta "seguir adelante" asumiendo un `organizationType = null` cuando en realidad el dato era inválido (no ausente legítimamente).
3. Falle recién dos capas más abajo, en `IssueCertificateUseCase::enforceOrganizationTypeRule()`, después de haber generado un par de llaves RSA y construido un CSR — trabajo desperdiciado, y el error real queda oculto en logs `warning` que nadie revisa.

Decisión del usuario (banking logic): **toda validación debe ocurrir al crear la solicitud**. Si un campo requerido no está presente y correctamente formateado según los valores válidos en BD, la solicitud **no se crea**. El job nunca debe recibir datos inválidos; si esto llegara a pasar, indica un bug de validación upstream y debe **fallar fuerte e inmediatamente** (visible en `failed_jobs`), sin reintentos y sin infraestructura de alertas nueva.

### Decisiones explícitas ya tomadas (no reabrir)

- **No se toca la migración/seed de `entity_document_types`** (catálogo `CC`/`PJ` desincronizado de producción `RM/ESAL/PROP/RNT/EXTRANJERAS/ESOL/RUNEOL/JUEGOS`) — se documenta como gap conocido en un comentario, la validación se hace dinámicamente contra el enum `OrganizationType::tryFrom()`, no contra IDs fijos.
- **No se toca el esquema** de `certificate_requests.entity_document_type_id` (sigue `NOT NULL DEFAULT 1`) — para Persona Natural (PN) el campo es irrelevante para la lógica de negocio (el job siempre retorna `null` de `organizationType` para PN), así que el default de BD no representa un riesgo real ahí. Para Persona Jurídica (PJ) se vuelve obligatorio y validado en la capa de aplicación.
- Sin alertas por email para fallos del job en este flujo — se reutiliza el mecanismo estándar de Laravel (`failed_jobs` + `Log::error` vía el método `failed()` ya existente en el job).
- `legal_rep_first_name`/`legal_rep_last_name` (requeridos-para-viafirma) se consolidan en el FormRequest, eliminando el chequeo duplicado y tardío del Handler.

---

## Hallazgos confirmados (trazado completo del flujo)

**Flujo de creación:** `POST /certificate-request` → `CertificateRequestController::createCertificateRequest()` → `CreateCertificateRequestFormRequest` (validación) → `CertificateRequestService::createCertificateRequest()` → `CreateCertificateRequestCommand` → `CreateCertificateRequestHandler::handle()` → `CertificateRequest::create([...])` → si `provider === 'viafirma'`, dispara `AutoIssueViafirmaJob::dispatch()` (sin gate de aprobación).

| # | Archivo:línea | Problema |
| --- | --- | --- |
| 1 | `CertificateRequestService.php:46` | `entityDocumentTypeId: (int) ($request->input('entity_document_type_id') ?? 1)` — default silencioso a `1` si el cliente lo omite. |
| 2 | `CreateCertificateRequestFormRequest.php:31` | `entity_document_type_id` es `sometimes` (opcional) y `exists` solo verifica que la fila existe, no que esté activa ni que su `code` mapee a un `OrganizationType` válido. No hay regla condicional atada a `type_organization_id`. |
| 3 | `AutoIssueViafirmaJob.php:127-158` (`resolveOrganizationType()`) | Para PJ, si `entityDocumentType` es null o `OrganizationType::tryFrom($code)` es null, hace `Log::warning(...); return null;` — el job sigue adelante, genera llave RSA y CSR, y falla 2 capas más abajo en `IssueCertificateUseCase::enforceOrganizationTypeRule()`. |
| 4 | `AutoIssueViafirmaJob.php:56-63` | Si `legal_rep_email` está vacío, `Log::warning(...); return;` — el job "termina" sin marcarse como fallido, invisible en `failed_jobs`. |
| 5 | `CreateCertificateRequestHandler.php:66-72` | Chequeo de `legal_rep_first_name`/`legal_rep_last_name` ocurre tarde (después de verificar cupo), no en el boundary HTTP. |
| 6 | Catálogo `entity_document_types` | Migración trackeada (`2026_06_06_103900_create_entity_document_types_table.php`) siembra `id=1→CC`, `id=2→PJ` — ninguno mapea a `OrganizationType`. Producción real (`cm_test`) tiene `id=1→RM, id=2→ESAL, id=3→PROP, id=4→RNT, id=5→EXTRANJERAS, id=6→ESOL, id=7→RUNEOL, id=8→JUEGOS, id=99→RM(era SRUES)`. **Fuera de alcance de este fix** — documentado como gap conocido. |

---

## Cambios por archivo

### 1. `app/Http/Requests/Certificate/CreateCertificateRequestFormRequest.php`

- Regla `entity_document_type_id`: cambiar de `['sometimes', 'integer', 'exists:entity_document_types,id']` a `['required_if:type_organization_id,1', 'nullable', 'integer', 'exists:entity_document_types,id']` (obligatorio solo cuando `type_organization_id === 1`, es decir Persona Jurídica).
- Agregar mensaje en `messages()`: `'entity_document_type_id.required_if' => 'El tipo de documento constitutivo es requerido para Persona Jurídica'`.
- Nuevo closure en `after()` (además del existente, no lo reemplaza) que, solo cuando `type_organization_id === 1` y `entity_document_type_id` está presente y existe:
  - Verifica `EntityDocumentType->active === true` → si no, error `"El tipo de documento constitutivo seleccionado no está activo."`
  - Verifica `OrganizationType::tryFrom($entityDocType->code) !== null` → si no, error `"El código '{$code}' del tipo de documento constitutivo no está soportado por Viafirma para perfiles FE-PJ."`
  - Comentario explicando por qué se valida contra el enum dinámicamente (catálogo real de BD no coincide con el seed trackeado).
- Dentro del closure existente que ya resuelve `$provider` (líneas 76-102): agregar el chequeo de `legal_rep_first_name`/`legal_rep_last_name` requeridos cuando `$provider === 'viafirma'` (movido desde el Handler).
- Agregar `use App\Models\EntityDocumentType;` y `use App\Modules\Viafirma\Domain\Enums\OrganizationType;`.

### 2. `app/Commands/Certificate/CreateCertificateRequestCommand.php`

- `entityDocumentTypeId` cambia de `int` a `?int` (nullable) — para PN legítimamente no hay valor, y no debe inventarse uno.

### 3. `app/Services/CertificateRequestService.php`

- Línea 46: eliminar el fallback `?? 1`. Usar `$request->filled('entity_document_type_id') ? (int) $request->input('entity_document_type_id') : null` (usa `filled()`, no `input() ?? null`, para tratar `""` también como ausente).

### 4. `app/Handlers/Certificate/CreateCertificateRequestHandler.php`

- Eliminar el bloque de líneas 66-72 (chequeo de `legal_rep_first_name`/`legal_rep_last_name`) — ahora vive en el FormRequest.
- En la construcción del array para `CertificateRequest::create([...])`: `entity_document_type_id` NO debe pasarse como `null` explícito (la columna es `NOT NULL`, sin `nullable()`, solo tiene `DEFAULT 1`) — debe **omitirse la clave del array** cuando `$command->entityDocumentTypeId === null`, dejando que el default de BD aplique (inofensivo para PN). Construir el array de atributos en una variable y agregar la clave condicionalmente antes de llamar a `create()`.

### 5. `app/Exceptions/Certificate/CertificateDataIntegrityException.php` (nuevo)

Sigue la convención de `CertificateIssuanceException.php` (mismo directorio, `RuntimeException`), pero sin las propiedades `httpStatus`/`providerName` (esta excepción nunca se renderiza a HTTP, solo se registra en logs/`failed_jobs`):

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Certificate;

use RuntimeException;

/**
 * Señala que un CertificateRequest llegó al pipeline de emisión con datos
 * estructuralmente inválidos que debieron ser rechazados en
 * CreateCertificateRequestFormRequest al momento de la creación.
 *
 * Su sola aparición indica un bug de validación en el boundary HTTP, nunca
 * un escenario de negocio legítimo. No se reintenta: el job se marca como
 * fallido de inmediato vía $this->fail($e).
 */
class CertificateDataIntegrityException extends RuntimeException
{
}
```

### 6. `app/Jobs/Certificate/AutoIssueViafirmaJob.php`

- Agregar `use App\Exceptions\Certificate\CertificateDataIntegrityException;`.
- Línea 49-54 (CR no encontrado): **sin cambios** — es una condición de carrera legítima (registro borrado entre el dispatch y la ejecución), no un problema de validación de datos.
- Líneas 56-63 (`legal_rep_email` vacío): reemplazar `Log::warning(...); return;` por:

```php
throw new CertificateDataIntegrityException(
    "CertificateRequest {$cr->id}: legal_rep_email vacío. Esto debió ser rechazado en CreateCertificateRequestFormRequest al crear la solicitud."
);
```

- `resolveOrganizationType()` líneas 137-142 (relación `entityDocumentType` nula para PJ): reemplazar `Log::warning(...); return null;` por:

```php
throw new CertificateDataIntegrityException(
    "CertificateRequest {$cr->id}: Persona Jurídica sin entity_document_type asociado. Esto debió ser rechazado en CreateCertificateRequestFormRequest al crear la solicitud."
);
```

- `resolveOrganizationType()` líneas 145-155 (código sin mapeo a `OrganizationType`): reemplazar `Log::warning(...); return null;` por:

```php
throw new CertificateDataIntegrityException(
    "CertificateRequest {$cr->id}: entity_document_type_id={$entityDocType->id} (code='{$entityDocType->code}') no mapea a ningún OrganizationType soportado por Viafirma. Esto debió ser rechazado en CreateCertificateRequestFormRequest al crear la solicitud."
);
```

- La rama PN (líneas 129-132, retorna `null` de inmediato) queda sin cambios — sigue siendo un `null` legítimo, no un error.
- En `handle()`, agregar un catch específico **antes** del `catch (Throwable $e)` genérico (líneas 91-105):

```php
} catch (CertificateDataIntegrityException $e) {
    Log::error('AutoIssueViafirmaJob: dato estructural inválido — fallo inmediato sin reintento', [
        'cr_id'   => $this->certificateRequestId,
        'attempt' => $this->attempts(),
        'error'   => $e->getMessage(),
    ]);
    $this->fail($e); // Marca el job como fallido de inmediato, no consume los 3 tries
    return;
} catch (Throwable $e) {
    // ...bloque existente sin cambios...
}
```

`fail()` está disponible vía el trait `InteractsWithQueue` ya usado por el job; dispara el método `failed()` existente (líneas 112-119), que ya registra `Log::error` — no se agrega infraestructura de alertas nueva.

---

## Notas / deuda técnica identificada pero fuera de alcance

- `tests/Unit/Commands/Certificate/CertificateCommandTest.php` ya falla hoy de forma independiente a este cambio (firma desactualizada de `CreateCertificateRequestCommand`/`UpdateCertificateRequestCommand` — parámetros obsoletos `files`/`postalCode`, faltan `entityDocumentTypeId`, `legalRepFirstName/LastName`, etc.). No se corrige en este cambio; queda como deuda técnica separada.
- No existen tests actuales que dependan del comportamiento viejo (`?? 1` o el `warning + return` silencioso), así que no hay regresiones esperadas de tests existentes.

---

## Verificación

1. `php artisan tinker` o test manual: crear una `CertificateRequest` PJ (`type_organization_id=1`) sin `entity_document_type_id` → debe rechazarse con 400 antes de tocar BD (mensaje `required_if`).
2. Crear una PJ con `entity_document_type_id` cuyo `code` no mapea a `OrganizationType` (ej. usando los valores reales de `entity_document_types` en la BD del entorno) → debe rechazarse con 400 en el `after()` hook.
3. Crear una PJ válida (código mapeado, activo) → debe crearse y el job debe completar sin lanzar `CertificateDataIntegrityException`.
4. Crear una PN sin `entity_document_type_id` → debe crearse normalmente (el campo no es requerido para PN), y `resolveOrganizationType()` debe retornar `null` sin error.
5. Revisar que `php artisan test --filter=CreateCertificateRequestFormRequest` (si existe) o pruebas manuales de la ruta `POST /certificate-request` no generen falsos positivos con Persona Natural.
6. Confirmar visualmente que, si se fuerza manualmente (vía tinker) un caso con dato estructural roto y se dispara `AutoIssueViafirmaJob::dispatchSync()`, el job termina en `failed_jobs` (o lanza la excepción directamente en modo sync) sin reintentar 3 veces.

---

## Archivos a crear/modificar (resumen)

| Archivo | Acción |
| --- | --- |
| `app/Http/Requests/Certificate/CreateCertificateRequestFormRequest.php` | Editar |
| `app/Commands/Certificate/CreateCertificateRequestCommand.php` | Editar |
| `app/Services/CertificateRequestService.php` | Editar |
| `app/Handlers/Certificate/CreateCertificateRequestHandler.php` | Editar |
| `app/Exceptions/Certificate/CertificateDataIntegrityException.php` | Crear |
| `app/Jobs/Certificate/AutoIssueViafirmaJob.php` | Editar |

---

## Implementación completada

**Fase 1:** [CertificateDataIntegrityException.php](../../app/Exceptions/Certificate/CertificateDataIntegrityException.php) — nueva excepción ✅

**Fase 2:** [CreateCertificateRequestCommand.php](../../app/Commands/Certificate/CreateCertificateRequestCommand.php) — `entityDocumentTypeId` nullable ✅

**Fase 3:** [CertificateRequestService.php](../../app/Services/CertificateRequestService.php) — eliminado fallback `?? 1` ✅

**Fase 4:** [CreateCertificateRequestFormRequest.php](../../app/Http/Requests/Certificate/CreateCertificateRequestFormRequest.php) — validación condicional y consolidada ✅

**Fase 5:** [CreateCertificateRequestHandler.php](../../app/Handlers/Certificate/CreateCertificateRequestHandler.php) — eliminado duplicado, omitida clave nula ✅

**Fase 6:** [AutoIssueViafirmaJob.php](../../app/Jobs/Certificate/AutoIssueViafirmaJob.php) — fallos sin reintento ✅

---

**Documento preparado por:** Claude Code  
**Estado:** ✅ IMPLEMENTADO — 2026-07-09  
**Validación:** Todas las 6 fases completadas. La lógica bancaria está en su lugar: validación en el boundary HTTP (FormRequest), omisión de valores implícitos, fallo inmediato para datos estructurales inválidos.
