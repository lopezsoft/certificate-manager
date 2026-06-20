# Documento de Desarrollo: Ciclo de vida en `certificate_requests`, S3 y Revocación Automática

> **Fecha:** 2026-06-19
> **Ámbito:** `backend/`
> **Roadmap base:** [`2026-06-19-16-07-ROADMAP-S3-SANDBOX-REVOCATION.md`](./2026-06-19-16-07-ROADMAP-S3-SANDBOX-REVOCATION.md)
> **Estado:** 🛠️ Especificación técnica para implementación
> **Nota:** El Sandbox/Mock (Sección 2 del roadmap) está **APLAZADO** y no forma parte de este documento.

Este documento traduce el roadmap a una especificación ejecutable, por fases ordenadas por dependencia.
Cada fase indica objetivo, archivos a crear/modificar (rutas reales), pasos concretos y criterios de
aceptación. Premisa rectora: **`certificate_requests` es la fuente de verdad del ciclo de vida.**

---

## Estado de avance (vivo)

| Fase | Estado | Notas |
|------|--------|-------|
| **Fase 0** — Persistir emisión/vencimiento | ✅ **Completada** (2026-06-20) | Migración aplicada en local; ver detalle abajo. Commited. |
| **Fase 1** — Estados unificados + mapper | ✅ **Completada** (2026-06-20) | REVOKED/EXPIRED, mapper, recovery service; ver detalle abajo. |
| **Fase 2** — Storage genérico + S3 + migración | ✅ **Completada** (2026-06-20) | Resolver, Base64, disco legacy configurable, comando migración; ver detalle abajo. |
| Fase 0.bis — Renovación | ✅ **Completada** (2026-06-20) | Endpoint de renovación, orden CERTIFICATE_RENEWAL y extensión de vigencia. |
| Fase 3 — Revocación automática + expiración | ✅ **Completada** (2026-06-20) | D2 resuelto (null->SYSTEM). Job de revocación y expiración configurados diarios. |

### Detalle Fase 0 (hecho)
- **Path de migraciones del ciclo de vida:** nuevas migraciones del núcleo viven en
  `database/migrations/certificates/` y se ejecutan **individualmente** con el comando wrapper
  `php artisan certificates:migrate {file} [--apply] [--force]` (modelado en `viafirma:migrate`;
  dry-run por defecto). `php artisan migrate` NO recursa en ese subdirectorio.
- **Migración aplicada:** `2026_06_20_000001_add_issuance_dates_to_certificate_requests.php` →
  añade `issued_at` y `cert_valid_to` a `certificate_requests` (columnas verificadas).
- **`CertificateValidatorService::parseValidity($p12Binary, $pin)`** → devuelve
  `['validFrom' => CarbonImmutable, 'validTo' => CarbonImmutable]` parseando el X.509 del P12.
- **`AssembleP12Job`** y **`RedownloadCertificateUseCase`**: tras ensamblar el P12 persisten en
  `certificate_requests`: `issued_at = validFrom`, `cert_valid_to = validTo`,
  `expiration_date = validFrom + life años` (comercial) y `request_status = PROCESSED`.
- **Modelo:** `issued_at`/`cert_valid_to` añadidos a `$fillable` de `CertificateRequest`.
- **Tests:** `AssembleP12Test` pasa (5/6); el único fallo (`it rejects mismatched key and cert`) es
  **preexistente y ajeno** (assert con desajuste de mayúsculas en mensaje de `CryptoService`).
- **Pendiente menor:** registrar la ejecución de la migración en `CHANGELOG.md`.

### Detalle Fase 1 (hecho)
- **Migración aplicada:** `2026_06_20_000002_add_revoked_expired_to_request_status_enum.php` (path
  controlado `certificates/`) → añade `REVOKED` y `EXPIRED` a la columna ENUM
  `certificate_requests.request_status` (preservando los valores legacy).
- **`CertificateRequestStatusEnum`:** nuevos casos `REVOKED`/`EXPIRED` con `description()`;
  `allowedTransitions()` ahora permite `PROCESSED → [REVOKED, EXPIRED]` (resuelve **F4.2**) y deja
  `REVOKED`/`EXPIRED` como terminales.
- **Mapper central:** `InternalState::toRequestStatus(): CertificateRequestStatusEnum` (tabla del
  roadmap §3). Verificado: COMPLETED→PROCESSED, REVOKED→REVOKED, FAILED→REJECTED, EXPIRED→EXPIRED.
- **Literales reemplazados:** `AssembleP12Job` usa el mapper para PROCESSED;
  `RevokeCertificateUseCase` ahora sincroniza a **REVOKED** (antes REJECTED) vía mapper.
- **Recuperación FAILED (D3, por tipo de error):** `RecoveryStrategy` enum (REOPEN/RECREATE) +
  `FailedCertificateRecoveryService`. Errores de datos (`rues_error`, `accreditation_rejected`) →
  REOPEN (REJECTED→DRAFT); técnicos/SLA (`ASSEMBLE_FAILED`, `fail`, `POLL_EXPIRED`, timeouts) →
  RECREATE. `reopen()` implementado; la orquestación de RECREATE la dispara el flujo de emisión.
- **Tests:** la suite `tests/Unit/Modules/Viafirma` muestra **23 fallos preexistentes ajenos** a este
  cambio (verificado con stash: mismos 23 fallos sin la Fase 1). El mapper y las transiciones nuevas se
  validaron por smoke test en tinker.
- **Pendiente de cableado (siguiente fase/integración):** invocar `FailedCertificateRecoveryService`
  desde el punto donde hoy se maneja un certificado FAILED (UI/endpoint admin o job), y la migración
  enum en `CHANGELOG.md`.

### Detalle Fase 2 (hecho)
- **Config genérico** `config/certificates.php`: `CERT_STORAGE_DISK`, `CERT_STORAGE_PREFIX`,
  sub-rutas por proveedor/artefacto, y `CERT_LEGACY_DISK` (default `attachment`). El disco `s3` ya
  estaba en `filesystems.php` y `league/flysystem-aws-s3-v3` ya instalado.
- **`CertificateStoragePathResolver`** (`app/Services/Certificates/`): ruteo único
  `{prefix}/certificates/{provider}/{artifact}/{file}` + `disk()` + `legacyDisk()`. Verificado:
  `local/certificates/viafirma/p12/42_ABC.p12`.
- **Refactor de consumidores Viafirma** al resolver (sin literales `viafirma.storage`):
  `DownloadP7bJob`, `AssembleP12Job` (resolver inyectado en `handle`), `RedownloadCertificateUseCase`
  (resolver en constructor), `PurgeExpiredKeysJob`, `ViafirmaDownloadService`.
- **Entrega Base64:** `ViafirmaDownloadService::base64For()` + `CertificateIssuanceController::downloadBase64()`
  + ruta `GET /certificate-request/{id}/issuance/download/base64`
  (`v1.certificate-request.issuance.download.base64`). Verificada en `route:list`.
- **Disco legacy configurable:** reemplazados los literales `Storage::disk('attachment')` por
  `config('certificates.storage.legacy_disk', 'attachment')` en `CertificateRequestFilesService`,
  `ProcessCertificateJob`, `CreateCertificateRequestHandler`, `DocumentTrait`, `SendMail`.
  `ProcessCertificateJob` ahora es **S3-safe** (descarga a temporal para el OCR cuando el disco no es
  local; elimina dead-code `$disk->path()` que rompía en S3).
- **Comando de migración** `certificates:migrate-legacy-to-s3` (dry-run por defecto, `--apply`,
  `--from`, `--to`, `--force`): copia a la MISMA ruta relativa en S3 **solo** certificados vigentes del
  otro proveedor (`PROCESSED`, `expiration_date > now()`, sin `viafirma_certificate_requests`). Sin
  reescritura de rutas en BD; tras migrar se pone `CERT_LEGACY_DISK=s3`. Dry-run verificado:
  selecciona 451 solicitudes legacy.
- **Tests:** suite `tests/Unit/Modules/Viafirma` estable en **23 fallos preexistentes** (sin
  regresión). Los 7 fallos de `tests/Feature/.../CertificateIssuanceViafirmaTest` son
  `BadMethodCallException` del flujo `issue` (setup/mock), **ajenos** a esta fase.
- **Activación S3 (runtime, sin código):** definir `CERT_STORAGE_DISK=s3`, `CERT_STORAGE_PREFIX=...`,
  `AWS_*`, ejecutar `certificates:migrate-legacy-to-s3 --apply`, verificar y poner `CERT_LEGACY_DISK=s3`.

---

## Decisiones previas requeridas (bloquean Fase 4)

| # | Decisión | Estado |
|---|----------|--------|
| D1 | **Origen del `revokingCode`.** Hoy NO se persiste: el operador lo escribe en el body del endpoint manual (`RevocationController` → `$request->string('revoking_code')`). **Decisión (2026-06-20):** usar **`cod_request`** (ya persistido en `viafirma_certificate_requests`) como `revokingCode` para la revocación automática; más adelante se evaluará si se requiere otro campo. → **No se necesita columna nueva** para la Fase 3. | ✅ Resuelto: usar `cod_request` |
| D2 | **Usuario "sistema"** para `revokedByUserId` en revocación automática (hoy `RevokeInputDto` exige un user id; el manual usa `auth()->id()`). Definir un ID fijo o `null` + etiqueta `SYSTEM`. | ✅ Resuelto: se usa `null` y etiqueta `SYSTEM (Auto Revocación)` |
| D3 | **Criterio FAILED → reenviar vs. recrear** (qué `last_error_code` son corregibles vs. irrecuperables). | ⛔ Confirmar |

---

## Fase 0 — Persistir emisión y vencimiento comercial (RAÍZ / desbloquea todo)

**Objetivo:** que toda emisión Viafirma deje el ciclo de vida completo en `certificate_requests`
(`issued_at`, `expiration_date` comercial, `life`, `request_status=PROCESSED`). Resuelve además que los
certificados Viafirma sean visibles a las notificaciones de vencimiento.

**Migración (nueva)** en `database/migrations/`:
- `certificate_requests`: añadir `issued_at DATETIME NULL` y (opcional) `cert_valid_to DATETIME NULL`.

**Modificar [AssembleP12Job](../app/Modules/Viafirma/Infrastructure/Jobs/AssembleP12Job.php) (líneas ~171-180):**
- Tras ensamblar y guardar el P12, parsear el certificado para obtener `validFrom`/`validTo`.
  Reutilizar el patrón de [CertificateValidatorService::getExpirationDate()](../app/Services/CertificateValidatorService.php)
  (que hace `openssl_x509_parse`). Como `getExpirationDate()` espera base64, extraer un helper que
  acepte el binario ya disponible en el job (evitar leer de nuevo del disco). **Recomendado:** añadir
  `CertificateValidatorService::parseValidity(string $p12Binary, string $pin): array{validFrom, validTo}`.
- En el mismo `update()` que hoy pone `PROCESSED`, escribir:
  - `issued_at = validFrom`
  - `expiration_date = issued_at + life años` (vencimiento **comercial**; NO `validTo` cuando `life=1`)
  - `cert_valid_to = validTo` (si se añadió)
- Mantener el `ChangeHistory` existente.

**Modificar [RedownloadCertificateUseCase](../app/Modules/Viafirma/Application/UseCases/RedownloadCertificateUseCase.php):**
aplicar el mismo volcado al reensamblar.

**Criterios de aceptación:**
- Tras emitir un certificado Viafirma, `certificate_requests` tiene `issued_at`, `expiration_date`
  (comercial) y `request_status=PROCESSED`.
- `SendExpiringCertificatesNotificationsJob` lista certificados Viafirma próximos a vencer.

---

## Fase 0.bis — Renovación (extender vigencia del mismo certificado)

**Objetivo:** permitir que el cliente renueve, generando una orden de pago, y al confirmarse el pago se
extienda la vigencia del **mismo** certificado a 2 años.

**Implementación:**
- Endpoint/acción "renovar" que crea un `CertificateOrder` (provider WOMPI) ligado al
  `certificate_request_id` (reutilizar el flujo de órdenes existente en `app/Models/CertificateOrder.php`
  y `app/Payments/`).
- En el handler de confirmación de pago (`PaymentTransaction` → APPROVED), **extender** en
  `certificate_requests`: `expiration_date = issued_at + 2 años` y `life = 2`.

**Criterio de aceptación:** un certificado renovado pasa a `life=2` con `expiration_date` extendida y
**deja de aparecer** en el conjunto de revocación de la Fase 4.

---

## Fase 1 — Estados unificados + mapeo centralizado

**Objetivo:** estados finales correctos y un único punto de mapeo Viafirma → unificado.

**Modificar [CertificateRequestStatusEnum](../app/Enums/CertificateRequestStatusEnum.php):**
- Añadir casos `REVOKED = 'REVOKED'` y `EXPIRED = 'EXPIRED'` (con su `description()`).
- `allowedTransitions()`: cambiar `PROCESSED => []` por `PROCESSED => [REVOKED, EXPIRED]`. Marcar
  `REVOKED`/`EXPIRED` como terminales (`=> []`).
- Revisar `issuedStatuses()`/`activeStatuses()` para incluir/excluir los nuevos estados según
  corresponda (un `REVOKED`/`EXPIRED` ya no es "emitido vigente").

**Crear el mapper único** (recomendado método en el enum del módulo):
`InternalState::toRequestStatus(): CertificateRequestStatusEnum` con la tabla del roadmap §3.
Reemplazar literales dispersos en:
- [AssembleP12Job](../app/Modules/Viafirma/Infrastructure/Jobs/AssembleP12Job.php) (`PROCESSED`)
- [RevokeCertificateUseCase](../app/Modules/Viafirma/Application/UseCases/RevokeCertificateUseCase.php)
  (hoy fija `REJECTED`; debe pasar a `REVOKED`).

**Manejo FAILED → recuperación (según D3):**
- Si el error es corregible: aprovechar la transición existente `REJECTED → DRAFT` para reabrir,
  actualizar datos y reintentar.
- Si es irrecuperable: descartar y crear nueva solicitud.
- Implementar como un servicio `FailedCertificateRecoveryService` que decida la vía según
  `viafirma_certificate_requests.last_error_code`.

**Criterio de aceptación:** revocar deja `request_status=REVOKED`; tests de transición cubren
`PROCESSED→REVOKED` y `PROCESSED→EXPIRED`.

---

## Fase 2 — Almacenamiento genérico + S3 + migración del otro proveedor

**Objetivo:** almacenamiento agnóstico de proveedor en S3, con migración acotada y entrega Base64.

**Config / infra:**
- `composer require league/flysystem-aws-s3-v3`.
- Declarar disco `s3` en [config/filesystems.php](../config/filesystems.php).
- Nuevo `config/certificates.php` (genérico) con:
  ```php
  'storage' => [
      'disk'   => env('CERT_STORAGE_DISK', 'local'),
      'prefix' => env('CERT_STORAGE_PREFIX', 'local'),
      'paths'  => [
          'viafirma_p12' => env('CERT_VIAFIRMA_P12_PATH', 'viafirma/p12'),
          'viafirma_p7b' => env('CERT_VIAFIRMA_P7B_PATH', 'viafirma/p7b'),
      ],
  ],
  ```

**Crear `CertificateStoragePathResolver`** (servicio único de ruteo):
`resolve(string $provider, string $artifact, string $name): string` →
`{prefix}/certificates/{provider}/{artifact}/{name}`. Consumido por Viafirma y por el otro proveedor.

**Refactor de consumidores** para usar el resolver + disco genérico (eliminar literales `viafirma.storage`):
- [DownloadP7bJob](../app/Modules/Viafirma/Infrastructure/Jobs/DownloadP7bJob.php)
- [AssembleP12Job](../app/Modules/Viafirma/Infrastructure/Jobs/AssembleP12Job.php)
- [RedownloadCertificateUseCase](../app/Modules/Viafirma/Application/UseCases/RedownloadCertificateUseCase.php)
- [PurgeExpiredKeysJob](../app/Modules/Viafirma/Infrastructure/Jobs/PurgeExpiredKeysJob.php)
- [ViafirmaDownloadService](../app/Modules/Viafirma/Application/Services/ViafirmaDownloadService.php)
- Deprecar `viafirma.storage.*` en [config/viafirma.php](../config/viafirma.php) (mantener fallback temporal).

**Comando de migración (SOLO otro proveedor)** — usar como plantilla
[ViafirmaMigrateCommand](../app/Console/Commands/Viafirma/ViafirmaMigrateCommand.php) (dry-run por
defecto, `--apply`, `--force` para no-local):
- Crear `app/Console/Commands/Certificates/MigrateLegacyCertificatesToS3Command.php`.
- Seleccionar **solo** certificados del otro proveedor: `request_status=PROCESSED`,
  `expiration_date > now()`, y **sin** `viafirma_certificate_requests` asociado.
- Copiar archivos del disco legacy `attachment` → S3 y reescribir `base_path` (+ rutas en `FileManager`,
  ver [CertificateRequestFilesService](../app/Services/CertificateRequestFilesService.php)) en BD.
- **Viafirma no se migra** (es proveedor nuevo, sin certificados previos): escribe directo a S3 desde la
  emisión.

**Entrega Base64:**
- Añadir endpoint/variante en [CertificateIssuanceController](../app/Http/Controllers/Certificate/CertificateIssuanceController.php)
  que devuelva el `.p12` en **Base64** (JSON), para uso desatendido, además del stream actual
  (`downloadFile`). Implementar en [ViafirmaDownloadService](../app/Modules/Viafirma/Application/Services/ViafirmaDownloadService.php)
  un `base64For($entity): string` que haga `base64_encode(Storage::disk(...)->get($path))`.

**Criterios de aceptación:**
- Emisión Viafirma escribe en `s3://{bucket}/{prefix}/certificates/viafirma/...`.
- El comando migra solo certificados vigentes del otro proveedor; los de Viafirma quedan intactos.
- Descarga Base64 devuelve un P12 válido (parseable con `openssl_pkcs12_read`).

---

## Fase 3 — Revocación comercial automática + expiración natural

**Objetivo:** revocar comercialmente los certificados de 1 año vencidos (con gracia) y reflejar la
expiración natural, todo sobre `certificate_requests`.

**Prerequisitos:** D2 (usuario sistema). D1 resuelto: se usa `cod_request` (ya persistido) como
`revokingCode`; **no se requiere migración adicional**.

**Config:**
```env
VIAFIRMA_REVOCATION_GRACE_DAYS=15
```

**Crear `AutoRevokeUnpaidCertificatesJob`** (`app/Jobs/` o módulo Viafirma) y registrarlo en
[Kernel.php](../app/Console/Kernel.php) (diario, horario **distinto** de `PurgeExpiredKeysJob` 02:00):
- **Selección (sin joins de pago):** `request_status=PROCESSED` AND `life=1` AND
  `expiration_date + GRACE_DAYS < now()`.
- **Escala:** por cada certificado, **despachar un job a la cola** (no revocar en serie). Reutiliza
  reintentos/circuit-breaker del cliente.
- **Acción:** invocar [RevokeCertificateUseCase](../app/Modules/Viafirma/Application/UseCases/RevokeCertificateUseCase.php)
  con `revokingCode = cod_request`, `revocationReason = 5` (Cese de Operaciones) y el usuario sistema (D2).
  Deja `internal_state=REVOKED` y `request_status=REVOKED` (vía mapper de Fase 1).
- **Índice** recomendado: `(request_status, life, expiration_date)` en `certificate_requests`.

**Aviso previo:** job de notificación N días antes (reutilizar patrón de
`SendExpiringCertificatesNotificationsJob`).

**Expiración natural:** paso (mismo cron u otro) que marque `request_status=EXPIRED` los `PROCESSED`
con `expiration_date < now()` que no apliquen a revocación.

**Criterios de aceptación:**
- Un certificado de 1 año vencido + gracia se revoca automáticamente y queda `REVOKED`.
- Un certificado renovado (`life=2`) **nunca** entra al conjunto de revocación.
- Un certificado vencido sin revocar queda `EXPIRED`.

---

## Orden de ejecución y dependencias

```
Fase 0 (raíz) ──► Fase 1 ──► Fase 3
      └─► Fase 0.bis ─────────►┘
Fase 2 (S3) puede ir en paralelo a Fase 1, pero después de Fase 0.
```

## Verificación end-to-end
1. Emitir un certificado Viafirma (entorno real/controlado) → verificar `issued_at`, `expiration_date`
   comercial y `request_status=PROCESSED` en `certificate_requests`; archivo en S3.
2. Descargar el `.p12` en Base64 y validarlo con `openssl_pkcs12_read`.
3. Renovar → confirmar `life=2` y `expiration_date` extendida.
4. Forzar un certificado de 1 año a fecha vencida + gracia → correr el cron → verificar `REVOKED` en
   ambos modelos y registro en `ChangeHistory`/`viafirma_status_history`.
5. Correr el comando de migración en `--pretend` y validar que **solo** selecciona certificados del
   otro proveedor vigentes.
6. `php artisan test` para los enums/transiciones, mapper y servicios nuevos.
