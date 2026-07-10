# Changelog

Todos los cambios notables de este proyecto están documentados en este archivo.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es/1.0.0/).
El versionado sigue [Semantic Versioning](https://semver.org/lang/es/).

---

## [Unreleased]

### Corregido — Viafirma: Error de Validación RUES (FE-PJ Identity Mismatch) + Patrón CN de FE-PN

- **Bug principal:** El campo `identity` en el payload enviado a Viafirma no coincidía con `serialNumber` en el CSR para FE-PJ
  - Causaba rechazo RUES: "Sus datos no coinciden con los encontrados en el Registro Único Empresarial"
  - Problema: payload enviaba cédula del representante, CSR contenía NIT de la empresa

- **Fix FE-PJ:** Función `resolveSubscriberIdentity()` en `IssueCertificateUseCase`:
  - **FE-PJ:** devuelve `$cr->dni` (NIT empresa) para coincidir con `serialNumber` en CSR
  - **FE-PN:** devuelve `$cr->document_number` (cédula representante)

- **Fix FE-PN:** Corregido patrón CN en `FePnCsrBuilder::dn()`
  - CN ahora sigue el patrón oficial: `{givenName} {surname} - {serialNumber}`
  - Antes faltaba el sufijo `- {serialNumber}`

- **Patrones DN oficiales de Viafirma** validados:
  - FE-PJ: `CN={legalNameCorp} - {departament},serialNumber={dnAlternativo1},...`
  - FE-PN: `CN={name} {lastName} - {identity},serialNumber={identity},...`

- **Consolidación de herramientas de diagnóstico:**
  - Eliminados comandos redundantes: `show:csr-content`, `dump:csr-raw`, `debug:viafirma-csr`, `debug:viafirma-payload`
  - Mantenidos: `analyze:csr-complete` (valida CSR con OpenSSL nativo, funciona en Windows), `debug:viafirma-submission` (valida payload JSON persistido)

- **Documentación:** `docs/2026-07-09-fix-rues-validation-error.md`

### Test Coverage

- `FePjCsrBuilderTest::test_builds_a_valid_csr_with_10_attributes` ✅ PASS (CN: `MI COMPANIA SAS - ANTIOQUIA`)
- `FePnCsrBuilderTest::test_builds_a_valid_csr_without_o_and_ou` ✅ PASS (CN: `Paula Ibarra - 1002000400`)

---

## [2.3.0] - 2026-05-15

### Añadido — Viafirma PKCS#10 Sprint 5: Hardening + Go-Live

- **`OpenSslCryptoService::assembleP12()`** — implementación real del ensamblaje PKCS#12:
  - Parseo de P7B en formato DER y PEM (`openssl_pkcs7_read` + regex fallback)
  - Identificación del certificado de entidad final vía `openssl_x509_check_private_key`
  - Separación automática de la cadena CA (`extracerts`)
  - Ensamblaje final con `openssl_pkcs12_export` + `friendly_name`
- **`AwsKmsKeyVault`** — implementación productiva del KeyVault:
  - Envelope encryption con `KMS GenerateDataKey` + AES-256-GCM local
  - Almacenamiento de `{encrypted_data_key, iv, tag, ciphertext}` en Cache
  - Driver activado en `ViafirmaServiceProvider` (`VIAFIRMA_KEY_VAULT_DRIVER=aws_kms`)
- **`ViafirmaFeatureGate`** — middleware de rollout gradual:
  - `VIAFIRMA_PKCS10_ENABLED=true|false` para kill-switch
  - `VIAFIRMA_PKCS10_ROLLOUT_PCT=10|50|100` para activación gradual por empresa (CRC32 determinístico de `company_id`)
  - Aplicado a todas las rutas `/api/v2/certificates/viafirma/*`
- **`ViafirmaHealthCheckCommand`** (`php artisan viafirma:health-check`):
  - Tabla de solicitudes por estado interno
  - Ratio de fallo con alerta si >5%
  - Solicitudes en `accreditation` >24h (alerta)
  - Solicitudes huérfanas (stalled)
  - Estado del circuit breaker (OPEN/CLOSED)
  - Estado del feature flag + porcentaje de rollout
  - Resumen de configuración con validación de campos requeridos
- **Runbook operativo** (`docs/runbooks/viafirma-incidents.md`):
  - 6 incidentes documentados: Circuit Breaker OPEN, accreditation >24h, solicitudes stalled, error ensamblaje P12, kill-switch emergencia, verificación de purga
  - Comandos de diagnóstico paso a paso
  - Tabla de contactos
- **Tests Sprint 5** (10 nuevos):
  - `AssembleP12Test` (6 tests): validación de inputs, P7B inválido, ensamblaje exitoso con round-trip PKCS12, detección de key/cert mismatch
  - `ViafirmaFeatureGateTest` (4 tests): allow enabled, block disabled, rollout 0%, rollout 100%

### Cambiado
- `config/viafirma.php` — añadida sección `feature_flag` (`enabled`, `rollout_percentage`)
- `ViafirmaServiceProvider` — activado driver `aws_kms` + registrado `ViafirmaHealthCheckCommand`
- `routes/api-v2.php` — middleware `ViafirmaFeatureGate` aplicado al grupo de rutas Viafirma

---

## [2.2.0] - 2026-05-15

### Añadido — Viafirma PKCS#10 Sprint 4: Descarga, Ensamblaje P12 y Entrega

- **`downloadP7b()`** en contrato `ViafirmaClient` + `GuzzleViafirmaClient`:
  - Descarga binaria del P7B desde `download_url` (distinta de `base_url`)
  - OAuth1 signing, Content-Type validation, error handling (transient vs fatal)
- **`DownloadP7bJob`** — descarga y persistencia del P7B:
  - `ShouldBeUnique` (5 min), retry en errores transient (`release(60)`)
  - Valida estado `READY_TO_DOWNLOAD` antes de ejecutar
  - Transiciona a `DOWNLOADED` y encadena `AssembleP12Job`
- **`AssembleP12Job`** — orquestación completa del ensamblaje:
  - Recupera llave privada del KeyVault
  - Genera PIN CSPRNG de 32 caracteres (`Str::random`)
  - Invoca `CryptoService::assembleP12()` con cadena CA
  - Guarda `.p12` en storage + PIN cifrado en KeyVault
  - Transiciona `DOWNLOADED → ASSEMBLED → COMPLETED` con historial
  - Limpieza de memoria (`unset` en material sensible)
- **`PurgeExpiredKeysJob`** — retención segura de llaves:
  - Purga `key_vault_ref` y `p12_password_ref` de solicitudes terminales tras 72h
  - Marcado como `PURGED` para evitar re-acceso
  - Cron diario a las 02:00 COT, `onOneServer`, `withoutOverlapping`
- **Endpoints de descarga** (2 nuevos):
  - `GET /api/v2/certificates/viafirma/{id}/download` — JSON con PIN + metadata + `expires_at` (24h)
  - `GET /api/v2/certificates/viafirma/{id}/download/file` — streaming binario P12 con `Content-Disposition: attachment`
- **`DispatchDownloadOnReadyListener`** — bridge Sprint 3 → Sprint 4:
  - Escucha `ViafirmaReadyToDownload` y despacha `DownloadP7bJob` con delay 10s
- **`ViafirmaCertificateReadyNotification`** — canal `database` para bell del frontend
- **Tests Sprint 4** (8 nuevos):
  - `DownloadAssemblePipelineTest`: guards de estado, detección PURGED, enum states, paths, purge marking

### Cambiado
- `EventServiceProvider` — añadido mapping `ViafirmaReadyToDownload → DispatchDownloadOnReadyListener`
- `ViafirmaServiceProvider` — 4 nuevos bindings de logger para jobs Sprint 4
- `Console/Kernel.php` — registrado `PurgeExpiredKeysJob` como cron diario
- `config/viafirma.php` — añadida sección `storage` (p7b_disk, p7b_path, p12_disk, p12_path)

---

## [2.1.0] - 2026-05-15

### Añadido — Viafirma PKCS#10 Sprint 3: Polling Asíncrono + State Machine + Resiliencia

- **`RemoteStatus`** enum — 14 estados remotos de Viafirma con clasificación semántica:
  - 6 métodos: `isProgressing()`, `isStopRecoverable()`, `isReadyToDownload()`, `isTerminalOk()`, `isTerminalFail()`, `shouldStopPolling()`
  - `toInternalState()` para mapeo a `InternalState`
- **`StateMachine`** — FSM del ciclo de vida:
  - `transition()` con guard clauses (no retroceder, no transicionar desde terminal)
  - Registro automático en `viafirma_status_history`
  - Despacho de eventos de dominio (`ViafirmaStatusChanged`, `ViafirmaRequestFailed`, `ViafirmaReadyToDownload`)
  - `markFailed()` / `markExpired()` para timeouts
- **`PollingScheduler`** — intervalos con backoff exponencial:
  - Fórmula: `base × min(2^floor(attempts/5), 8) + jitter(±20%)`
  - Intervalos por estado remoto (30s–300s base)
  - SLA: 72h máximo, 96 intentos máximo
- **`ViafirmaCircuitBreaker`** — protección ante 5xx repetidos:
  - Cache-backed (CLOSED/OPEN/HALF_OPEN)
  - Threshold configurable (default 5 fallos → pausa 5 min)
- **`PollViafirmaStatusJob`** — polling auto-reagendable:
  - `ShouldBeUnique`, 6 guards antes del polling
  - Integra circuit breaker, FSM transition, auto-reschedule
  - Tags `viafirma:poll:{id}` para Telescope
- **`ReviveStalledViafirmaPollsJob`** — watchdog cron cada 15 min:
  - Detecta solicitudes huérfanas (`next_poll_at < now() - 20min`)
  - Re-arma polling automáticamente
- **3 eventos de dominio**: `ViafirmaStatusChanged`, `ViafirmaRequestFailed`, `ViafirmaReadyToDownload`
- **`NotifyClientOnAccreditationListener`** — notifica al cliente cuando requiere KYC:
  - Construye URL KYC pública
  - Envía `ViafirmaAccreditationPendingNotification` (canal `database`)
- **`getStatus()`** en `ViafirmaClient` + `GuzzleViafirmaClient` + `StatusResultDto`
- **Tests Sprint 3** (23 nuevos):
  - `StateMachineTest` (10): transiciones, guards, dispatch de eventos
  - `PollingSchedulerTest` (7): backoff, jitter, SLA 72h con `Carbon::setTestNow`
  - `RemoteStatusTest` (6): clasificación semántica de los 14 estados

### Cambiado
- `config/viafirma.php` — añadidas secciones `polling`, `circuit_breaker`
- `EventServiceProvider` — mapping `ViafirmaStatusChanged → NotifyClientOnAccreditationListener`
- `ViafirmaServiceProvider` — bindings para StateMachine, PollingScheduler, CircuitBreaker
- `Console/Kernel.php` — registrado `ReviveStalledViafirmaPollsJob` cada 15 min

---

## [2.0.0] - 2026-05-15

### Añadido — Viafirma PKCS#10 Sprints 0–2: Fundación + Emisión Zero-Touch

- **Módulo `App\Modules\Viafirma`** — arquitectura hexagonal completa:
  - `Domain/` — contratos, enums, excepciones, value objects
  - `Application/` — use cases, commands, DTOs, services
  - `Infrastructure/` — Guzzle client, OpenSSL crypto, KeyVault, persistence
  - `Presentation/` — controller REST, form request, API resource
- **Sprint 1 — Cripto + Auth OAuth1**:
  - `OpenSslCryptoService` — generación RSA-2048 + CSR PKCS#10
  - `OAuth1Signer` — firma HMAC-SHA1 para autenticación con Viafirma
  - `EncryptedLocalKeyVault` — custodia AES-256-CBC con APP_KEY
  - `SafePemLogger` — redacción automática de material PEM en logs
  - `GuzzleViafirmaClient` — client HTTP con `getProfiles()`, `submitCsr()`, `getPublicId()`
  - 2 perfiles soportados: FE-PJ (Persona Jurídica) y FE-PN (Persona Natural)
  - Validación ISO-3166 para campos de país
  - `openssl.cnf` empaquetado (independiente del SO)
- **Sprint 2 — Endpoints de Emisión**:
  - `POST /api/v2/certificates/viafirma/issue` — emisión Zero-Touch completa
  - `GET /api/v2/certificates/viafirma/{id}` — detalle con historial
  - `GET /api/v2/certificates/viafirma` — listado paginado con filtros
  - `IssueCertificateUseCase` con Command Pattern
  - `IssueCertificateFormRequest` con validación tipada
  - `ViafirmaCertificateResource` (API Resource)
  - Swagger/OpenAPI con tag `v2 - Viafirma Certificados`
- **Migraciones** (3):
  - `add_legal_rep_fields_to_certificate_requests` — campos de representante legal
  - `create_viafirma_certificate_requests_table` — tabla principal del módulo
  - `create_viafirma_status_history_table` — auditoría de transiciones
- **Artisan commands**: `viafirma:migrate`, `viafirma:migrate-status`
- **Tests base** (12):
  - 4 unit (domain validation) + 8 feature (HTTP layer)

### Notas
- Cero dependencias nuevas — usa componentes nativos de Laravel
- Cero cambios en `.env` o `docker-compose.yml`
- Roadmap documentado en `docs/2026-05-14-10-00-ROADMAP-INTEGRACION-VIAFIRMA-RA-PKCS10.md`

---

## [1.9.1] - 2026-07-09

### Añadido — Banking Logic Validation + Viafirma KYC Persistence + Refactorización IssueCertificateUseCase

- **Banking logic validation en CreateCertificateRequest**:
  - Nueva excepción `CertificateDataIntegrityException` para fallos de integridad estructural en jobs (no se reintenta)
  - `CreateCertificateRequestFormRequest` valida todos los campos requeridos antes de crear la solicitud
  - Validación condicional: `entity_document_type_id` obligatorio SOLO para Persona Jurídica (`type_organization_id === 1`)
  - Validación de `entity_document_type_id` mapea a `OrganizationType::tryFrom()` dinámicamente (catalogo real vs seed desincronizado)
  - `legal_rep_first_name` y `legal_rep_last_name` requeridos SOLO para proveedor Viafirma
  - Resultado: `AutoIssueViafirmaJob` recibe datos validados, falla fuerte sin reintento si hay inconsistencias

- **Nuevo campo: `country_id` en `certificate_requests`**:
  - Nueva columna `country_id INT DEFAULT 45 (Colombia)` con FK a `countries`
  - Agregada validación en FormRequest y relación BelongsTo en modelo
  - Usado en `IssueCertificateUseCase` en lugar de `company.country` — descentralización de datos

- **Refactorización de `IssueCertificateUseCase`**:
  - Eliminados todos los fallbacks de datos de empresa (`company.country`, `company.city`, etc.)
  - Ahora obtiene TODOS los datos de la solicitud: `country`, `city.department` vía `CertificateRequest`
  - Cambio de eager-load: `with(['company.country', 'company.city.department'])` → `with(['country', 'city.department'])`
  - `organizationUnit` ahora es configurable vía `config('viafirma.organization_unit')` en lugar de hardcodeado
  - Impacto: lógica centralizada, sin dependencias circulares de la empresa

- **Correcciones de enums vacíos en `file_managers`** (producción):
  - Llenar `document_type` vacío para archivos P7B → `P7B_CERTIFICATE`
  - Llenar `status` vacío para `private_key_reference` → `COMPLETED`
  - Llenar `status` vacío para archivos ZIP → `COMPLETED`
  - Llenar `document_type` vacío para ZIP → `CERTIFICATE`
  - Nuevas migraciones + DDLs manuales para ejecución en producción

- **Ajuste de polling expiration**: `VIAFIRMA_POLL_EXPIRATION_HOURS` de 72 → 96 horas (4 días, no 3)
  - Configurado en `config/viafirma.php` y `.env`
  - Permite mayor margen para acreditación KYC y procesos administrativos

- **Eliminación de parámetro no utilizado**:
  - Removido `identity` de `CsrInputDto` — los builders FE_PJ/FE_PN nunca lo usaban
  - `identity` persiste en `SubmitCsrInputDto` para el payload API de Viafirma (separación clara de responsabilidades)

- **Persistencia automática del link KYC**:
  - Nueva columna `kyc_accreditation_link` en `viafirma_certificate_request_states` para cachear el link de acreditación
  - Nuevo evento de dominio `ViafirmaAccreditationReached` — se dispara al entrar en estado remoto `accreditation`, independientemente de cambios en `internal_state`
  - Nuevo listener `DispatchKycLinkFetchListener` — despacha job automático para capturar el link
  - Nuevo job `FetchKycAccreditationLinkJob` (`ShouldQueue`, `ShouldBeUnique`) con reintentos y manejo de errores idempotente
  - Ventaja: El link persiste en BD incluso si Viafirma avanza el estado más allá de `accreditation`, evitando pérdida del recurso

- **Tests de cobertura** (18 nuevos para KYC link):
  - `GetKycLinkUseCaseTest` (6 tests): caché, error con estado real, on-demand, persistencia
  - `FetchKycAccreditationLinkJobTest` (5 tests): persistencia, idempotencia, manejo de errores transitorio/no-transitorio
  - `KycLinkControllerTest` (4 tests): endpoint 200, 422, 404, on-demand
  - `StateMachineAccreditationTest` (3 tests): evento dispara en transiciones internas de POLLING

### Corregido
- **Bug en `GetKycLinkUseCase`** — mensaje de error HTTP 422 siempre mostraba "Estado remoto actual: null":
  - Ahora usa `$entity->state?->remote_status` en lugar de `$entity->remote_status` (que no existe en el modelo raíz)
  - Resultado: mensajes de error ahora dicen el estado remoto real (ej. "rues_check", "submitted", etc.)

- Descripción del tag `Viafirma` en Swagger: ahora claramente documenta que el link KYC se captura automáticamente y persiste
- Schema `RevocationRequest` en Swagger: corregida descripción incorrecta que confundía kyc-link con revocation_code

### Cambiado
- Versión API actualizada de 1.9.0 → 1.9.1 en `SwaggerDefinitions.php`
- `CertificateRequest` modelo: agregado `country_id` a fillable y relación BelongsTo
- `ViafirmaCertificateRequestState` modelo: agregado `kyc_accreditation_link` a `$fillable`
- `IssueCertificateUseCase`: refactorización de datos (company → CertificateRequest directo)
- `StateMachine::transition()`: agregada lógica de evento `ViafirmaAccreditationReached` incondicionalmente al entrar en `accreditation`
- `EventServiceProvider`: registrado nuevo listener para `ViafirmaAccreditationReached`
- `AutoIssueViafirmaJob`: agregar `use CertificateDataIntegrityException` + catch específico

---

## [1.9.0] - 2026-02-20

### Añadido
- **Command Pattern en `CertificateRequestService`** (T-14):
  - `app/Commands/Certificate/` — interfaz marcadora + 4 DTOs `readonly` (`CreateCertificateRequestCommand`, `UpdateCertificateRequestCommand`, `UpdateCertificateStatusCommand`, `DeleteCertificateRequestCommand`)
  - `app/Handlers/Certificate/` — 4 handlers con responsabilidad única (`CreateCertificateRequestHandler`, `UpdateCertificateRequestHandler`, `UpdateCertificateStatusHandler`, `DeleteCertificateRequestHandler`)
  - `CertificateRequestService` reescrito como fachada delgada: valida → construye Command → delega al handler
  - `AppServiceProvider` actualizado con 4 singletons de handlers
- **Suite de tests automatizados — 165 tests / 0 fallos** (regla: solo mocks, sin DB):
  - `tests/Unit/Commands/Certificate/CertificateCommandTest` — 18 tests (DTOs readonly, interfaz, tipos)
  - `tests/Unit/Handlers/Certificate/CertificateHandlerStructureTest` — 17 tests (Reflection, firmas, namespaces)
  - `tests/Unit/Handlers/Certificate/UpdateCertificateStatusHandlerNotificationTest` — 8 tests (Mockery, lógica de notificaciones)
  - `tests/Unit/Jobs/SendMonthlyCompanyCertificatesReportJobTest` — 11 tests (T-03)
  - `tests/Unit/Jobs/SendAdminExpiringCertificatesReportJobTest` — 8 tests (T-04)
  - `tests/Feature/AutomatedManualNotificationsTest` — 11 tests, convierte scripts tinker en tests automatizados (T-08)
- **Migraciones de webhooks** ejecutadas: `webhook_endpoints` y `webhook_deliveries`

### Cambiado
- Eliminados 6 tests boilerplate de Laravel Breeze (`tests/Feature/Auth/*`, `tests/Feature/ProfileTest`) que usaban `RefreshDatabase` y ejecutaban SQL contra la DB
- Import muerto `RefreshDatabase` limpiado de `tests/Feature/ExampleTest`

### Completado (Backlog)
| Tarea | Descripción |
|-------|-------------|
| T-01 | DI en controllers |
| T-02 | Tests `SendExpiringCertificatesNotificationsJob` |
| T-03 | Tests `SendMonthlyCompanyCertificatesReportJob` |
| T-04 | Tests `SendAdminExpiringCertificatesReportJob` |
| T-05 | Tests `NotificationController` (5 endpoints) |
| T-06 | Tests endpoints PAT (`/v1/tokens`) |
| T-07 | Tests módulo Webhooks |
| T-08 | Automatizar scripts tinker de notificaciones |
| T-09 | Jerarquía de excepciones custom |
| T-10 | Handler global en `app/Exceptions/Handler.php` |
| T-11 | Middleware `throttle` en endpoints sensibles |
| T-12 | Sanitización de inputs con `strip_tags` + `Str::upper()` |
| T-13 | Validación de MIME type real en uploads |
| T-14 | Refactorización `CertificateRequestService` con Command Pattern |
| T-23 | `APP_VERSION` corregido a `1.9.0` |

---

## [1.8.0] - 2026-02-19

### Añadido
- **Sistema de notificaciones de vencimientos** — corrección y completado del sistema existente:
  - `NotificationController` con 5 endpoints para el SPA:
    - `GET /v1/certificates/expiring` — lista certificados PROCESSED próximos a vencer
    - `GET /v1/notifications` — notificaciones persistidas del usuario autenticado
    - `POST /v1/notifications/{id}/read` — marcar notificación individual como leída
    - `POST /v1/notifications/read-all` — marcar todas las notificaciones como leídas
    - `POST /v1/admin/certificates/notify-now` — disparar notificaciones manualmente (solo admin)
  - Canal `database` activado en `CertificateExpiringNotification` (persiste en tabla `notifications`)
  - Comandos Artisan para testing y triggers manuales:
    - `php artisan certificates:notify-expiring [--dry-run] [--days=30]`
    - `php artisan certificates:admin-report [--weekly]`
    - `php artisan certificates:monthly-report [--admin-only] [--company-id=]`
- **Swagger**: tag `Notificaciones`, schemas `ExpiringCertificate` y `NotificationItem`, versión `1.8.0`

### Corregido
- **Bug scheduler mensual**: `monthlyOn(Carbon::now()->endOfMonth()->day)` evaluaba el día en el arranque del proceso (día fijo). Reemplazado por `lastDayOfMonth()` para ejecución dinámica correcta cada mes.
- **Filtro de solicitudes en Job**: `SendExpiringCertificatesNotificationsJob` ahora filtra `request_status = PROCESSED`, evitando notificar certificados de solicitudes canceladas, rechazadas o en proceso.

---

## [1.7.0] - 2026-01-27

### Añadido
- Sistema de Personal Access Tokens (PAT) para integraciones externas
- Endpoints CRUD para tokens: crear, listar, revocar, renovar
- Documentación Swagger de todos los endpoints de tokens
- Guía de uso de API tokens para desarrolladores (`docs/`)
- Documentación sobre refresh tokens en sistemas PAT (`docs/`)

---

## [1.6.0] - 2026-01-20

### Añadido
- Sistema de webhooks salientes para eventos de certificados
- Endpoints para gestionar webhook endpoints: crear, listar, actualizar, eliminar
- Rotación de secretos de webhook
- Reintentos automáticos de entregas fallidas (`WebhookRetryCommand`)
- Limpieza de deliveries antiguos (`WebhookCleanupCommand`)
- Documentación Swagger de webhooks y guía de integración frontend

---

## [Anteriores]

Versiones anteriores a 1.6.0 no documentadas en este archivo.
