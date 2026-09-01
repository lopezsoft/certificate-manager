# Changelog

Todos los cambios notables de este proyecto están documentados en este archivo.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es/1.0.0/).
El versionado sigue [Semantic Versioning](https://semver.org/lang/es/).

---

## [Unreleased]

### Añadido — Viafirma: redirección KYC configurable por empresa

- **Requisito:** cada empresa debe poder definir su propia página de redirección tras completar la verificación KYC (MetaMap), editable desde el front, en vez de usar siempre la página global por defecto.
- **Sin tabla/UI nueva** — se reutilizó el mecanismo existente de configuración por empresa: `general_settings` (catálogo global) + `general_setting_companies` (override por empresa), el mismo patrón que ya usa `NOTIFICATIONEMAIL` (ver `CheckSuppressedEmail::handle()`).
- **DDL** (no ejecutado): `database/migrations/viafirma/2026_08_30_120000_add_kyc_redirect_url_general_setting.sql` — inserta el nuevo setting global `VIAFIRMA_KYC_REDIRECT_URL` con `value = NULL` (la columna `value` de `general_settings` no alcanza para una URL completa — confirmado con error `Data too long`; no se alteró la tabla). El default real sigue viviendo en `config/viafirma.php` + `.env`; el override por empresa se guarda en `general_setting_companies.value` (VARCHAR(250), sí alcanza). ⚠️ Requiere confirmar el `tag` (categoría) antes de ejecutar.
- **`ViafirmaCertificateRequestRepository::findByPublicId()`**: ahora hace eager-load de `company.settings.setting` para poder resolver el override sin N+1.
- **`ViafirmaKycCallbackController`**: antes de calcular el destino global, busca si la empresa dueña de la solicitud tiene un override activo de `VIAFIRMA_KYC_REDIRECT_URL` (valor no vacío) y lo usa directamente como URL completa; si no, cae al comportamiento anterior (`FRONTEND_URL` + `VIAFIRMA_KYC_COMPLETED_PATH`).
- Tests nuevos (mockeados, sin BD): override presente → se usa; override con valor vacío → se ignora y cae al default. Los 4 tests del controlador pasan.
- **Pendiente:** confirmar el `tag` correcto en el DDL, ejecutarlo en producción, y que el equipo de frontend exponga este campo en la UI de configuración de empresa que ya gestiona `general_setting_companies`.

### Corregido — `WelcomeUserNotification` fallaba al adjuntar el manual de usuario

- **Bug real en producción:** `Call to undefined method MailMessage::attachments()` — el método correcto en esta versión de Laravel es `attach()` (singular), que acepta una única instancia de `Attachment` (o se llama varias veces para múltiples adjuntos), no `attachments()` con un array.
- **Fix:** cambiado a `->attach(Attachment::fromPath(...)->as(...)->withMime(...))`.
- Verificado invocando `toMail()` directamente (sin BD, sin enviar correo real): ya no lanza excepción y el adjunto queda registrado correctamente.

### Corregido — `SendExpiringCertificatesNotificationsJob` fallaba con TypeError al notificar empresas

- **Bug real en producción:** `CompanyExpiringCertificatesNotification::__construct()` exige `App\Models\Company`, pero el job obtenía la empresa vía `DB::table('companies')->first()` (Query Builder crudo, deliberado para "no traversar relación Eloquent"), que retorna `stdClass` — `TypeError: Argument #1 ($company) must be of type App\Models\Company, stdClass given`.
- **Fix:** `App\Models\Company::find($companyId)` — mismo costo (un SELECT por PK), pero retorna el tipo correcto. El guard existente (`if (!$company || !$company->email)`) ya maneja `null` igual que antes.

### Corregido — Viafirma: nombres de empresa largos rompían la generación de CSR (`asn1 ... string too long`)

- **Bug real en producción:** `openssl_csr_new falló: error:06800097:asn1 encoding routines::string too long` al emitir para "JUNTA ADMINISTRADORA DEL ACUEDUCTO Y ALCANTARILLADO DE LA VEREDA SAN FRANCISCO" (78 caracteres, solicitud 1213).
- **Causa raíz:** `AbstractOpenSslCsrBuilder::build()` ya tenía un parche que truncaba `CN`/`O`/`OU` a 64 caracteres (límite ASN.1), pero comparaba contra las claves largas (`commonName`, `organizationName`, `organizationalUnitName`) que **nunca existen** en el array `$dn` — `FePjCsrBuilder`/`FePnCsrBuilder` usan las claves cortas que espera `openssl_csr_new()` (`CN`, `O`, `OU`). El truncado nunca se ejecutaba; era código muerto.
- **Fix:** corregidas las claves del mapa de límites a `CN`/`O`/`OU`.
- Verificado reproduciendo el caso exacto (mismo nombre de 78 caracteres) con `FePjCsrBuilder` real + `openssl.cnf` empaquetado: antes fallaba, ahora genera el CSR correctamente con `CN`/`O` truncados a 64 caracteres.

### Corregido — Viafirma: 404 `request_not_found` reintentaba cada 30s para siempre

- **Bug real observado:** en sandbox de Viafirma, `GET /request/{codRequest}/status` devolvió `404 {"errorCode":"request_not_found"}` (el `codRequest` ya no existe del lado de Viafirma — su sandbox purga solicitudes de prueba periódicamente). Al no ser 5xx/429, no calificaba como `TransientHttpException`; caía como `ViafirmaClientException` genérica sin catch específico en `PollViafirmaStatusJob`, disparando el hook `failed()` (fix de esta misma sesión) que reprogramaba en 30s — **reintentando indefinidamente contra un recurso que nunca volverá a existir**.
- **`ViafirmaRequestNotFoundException`** (nueva, extiende `ViafirmaClientException`): `GuzzleViafirmaClient::send()` la lanza específicamente cuando el status es 404 y el body trae `errorCode: request_not_found`. Cualquier otro 404 sigue siendo `ViafirmaClientException` genérica (comportamiento sin cambios).
- **`PollViafirmaStatusJob`**: nuevo catch específico para esta excepción — marca `FAILED`/`REQUEST_NOT_FOUND`, `next_poll_at = null`, log `critical`, **sin reprogramar**. Terminal por diseño: reintentar nunca cambiará el resultado.
- Verificado con `GuzzleHttp\Handler\MockHandler` reproduciendo el payload exacto del error real (sin red ni BD): la excepción específica se lanza correctamente, y se confirmó sin regresión que un 404 con otro `errorCode` sigue siendo genérico y un 500 sigue siendo `TransientHttpException`.

### Añadido — Viafirma: callback propio post-verificación KYC (MetaMap) + redirección final

- **Origen:** Viafirma confirmó que MetaMap soporta redirección nativa al finalizar la verificación (`&redirect={url}&target=_self` en el link de acreditación, `GET /services/accreditation/{codRequest}`). Se diseñó para que el `redirect` apunte a un callback propio (no directo a una URL externa), de forma que quede registrado en BD que el cliente completó el flujo, antes de reenviarlo al destino final.
- **Nuevas columnas** en `viafirma_certificate_request_states` (migración creada, **pendiente de ejecución manual** — ver `database/migrations/viafirma/README.md`): `kyc_flow_completed_at`, `kyc_flow_completed_ip`, `kyc_flow_completed_user_agent`. Es solo una señal de UX/analytics — **no** confirma aprobación real de Viafirma (eso lo sigue determinando exclusivamente el polling vía `/status`); no transiciona la FSM.
- **`RecordKycFlowCompletedUseCase`** (nuevo): busca la solicitud por `public_id` (identificador opaco, no el `id` interno secuencial — evita enumeración en un endpoint público sin auth) y registra la visita.
- **`ViafirmaKycCallbackController`** (nuevo) — `GET /api/v1/viafirma/kyc-callback/{publicId}`, ruta pública sin `auth:api` (el suscriptor final nunca tiene sesión), con `throttle:30,1`. Registra la visita y redirige a `FRONTEND_URL` + `VIAFIRMA_KYC_COMPLETED_PATH` (página propia de "verificación completada", sigue el mismo patrón hash-route que `VerifyEmailController`).
- **`AppendsKycRedirectParams`** (nuevo trait, compartido entre `GuzzleViafirmaClient` y `MockViafirmaClient`): construye la URL del callback vía `route('viafirma.kyc-callback', ['publicId' => ...])` y la inyecta como `redirect` en el link de MetaMap (fusiona con la query existente vía `parse_url`/`parse_str`/`http_build_query`, no concatena texto a ciegas). Aplica automáticamente tanto a la captura automática (`FetchKycAccreditationLinkJob`) como al endpoint on-demand (`GetKycLinkUseCase`) — `ViafirmaClient::getAccreditationLink()` ahora requiere `$publicId` además de `$codRequest`.
- **Config nueva** en `config/viafirma.php`: `VIAFIRMA_KYC_CALLBACK_ENABLED` (default `true`), `VIAFIRMA_KYC_REDIRECT_TARGET` (default `_self`), `VIAFIRMA_KYC_COMPLETED_PATH` (default `/#/viafirma/verificacion-completada`).
- Verificado end-to-end con `php artisan tinker` (sin ejecutar queries): `route('viafirma.kyc-callback', ...)` resuelve correctamente, `MockViafirmaClient::getAccreditationLink()` genera la URL completa con el callback embebido, y la construcción del destino final produce la URL hash-route esperada.
- **Pendiente:** ejecutar la migración de las 3 columnas nuevas antes de desplegar este código, y confirmar/crear la página `/#/viafirma/verificacion-completada` en el frontend (o ajustar `VIAFIRMA_KYC_COMPLETED_PATH` a una ruta existente).
- **Mockeable, sin BD:** `RecordKycFlowCompletedUseCase` depende de `ViafirmaCertificateRequestRepositoryContract` (se agregó `findByPublicId()` al contrato + implementación) en vez de Eloquent directo, para poder testearse con mocks. Se quitó `final` de `SafePemLogger` y de `RecordKycFlowCompletedUseCase` — Mockery no puede mockear clases `final` por proxy; quitarlo es un cambio seguro (nunca rompe a quien ya las use). Tests nuevos: `RecordKycFlowCompletedUseCaseTest` (3 casos) y `ViafirmaKycCallbackControllerTest` (2 casos) — 100% mocks/instancias en memoria, verificado que pasan sin tocar ninguna tabla.

### Añadido — Viafirma: anexar documentos de soporte para organizaciones sin RUES (manual RA §2.3.7)

- **Contexto:** organizaciones con `entity_document_type_id = 99` (sin Registro Mercantil — consorcios, propiedad horizontal sin RUES, etc.) no pueden completar la verificación automática de Viafirma. El manual RA requiere adjuntar documentación de soporte (acta de constitución, nombramiento de administrador) para revisión manual — confirmado directamente con soporte de Viafirma.
- **`ViafirmaClient`:** nuevos métodos `uploadFiles()` (`POST /files/upload/`) y `listFiles()` (`GET /files/list/{codRequest}`), implementados en `GuzzleViafirmaClient` y `MockViafirmaClient` (sandbox).
- **`UploadSupportingDocumentsJob`** (nuevo, cola `viafirma-poll`): reutiliza los archivos ya adjuntos a la solicitud vía `POST /certificate-request/{id}/files` (`document_type=ATTACHED`, flujo existente — no se creó un endpoint de carga nuevo), los codifica en base64 y los sube a Viafirma. Idempotente: consulta `listFiles()` primero para no volver a subir archivos con el mismo nombre.
- **Trigger:** `IssueCertificateUseCase` lo despacha automáticamente (20s de delay) justo después de someter el CSR, cuando `certificate_requests.entity_document_type_id === 99` — no se espera a un estado de error (`docRequired`/`rues_error`), ya que estas solicitudes fallarán la verificación automática por diseño.

### Añadido — Viafirma: certificado emitido a nombre de titular distinto (validación de identidad post-emisión)

- **Incidente real:** Viafirma aprobó una biometría de acreditación equivocada (esposa en vez del titular del CSR) y emitió el certificado a nombre de la persona incorrecta (solicitud A152B9Q7L). Error del lado del proveedor, no de nuestro código — pero nosotros entregamos el P12 sin detectarlo.
- **`OpenSslCryptoService`:** nuevos métodos `extractSubjectIdentity()` (serialNumber del certificado emitido) y `extractCsrSubjectIdentity()` (serialNumber de la CSR original). `assembleP12()` refactorizado para compartir la lógica de localización del certificado de entidad final (`findEndEntityCertificate()`) sin cambiar su comportamiento.
- **`IdentityMismatchException`** — nueva excepción de dominio, terminal y no reintentable.
- **`AssembleP12Job`** y **`RedownloadCertificateUseCase`**: antes de generar/regenerar el P12, comparan el `serialNumber` de la CSR real almacenada contra el del certificado emitido. Si no coinciden: `FAILED`/`IDENTITY_MISMATCH`, log `critical`, **no se genera el P12** ni se reintenta automáticamente (evita que `AutoRedownloadPendingViafirmaJob` lo reintente sin sentido — el resultado nunca cambiaría).
- **Corrección durante la verificación:** la primera versión comparaba contra `request_payload['identity']` (`$cr->document_number`), pero el `serialNumber` de la CSR se construye con `$cr->dni` — campos distintos para FE-PJ (NIT vs. cédula del representante). Habría bloqueado certificados FE-PJ legítimos. Corregido comparando contra la CSR real (`csr_pem`), no contra un campo de negocio. Verificado end-to-end con OpenSSL real (CSR + certificado autofirmado sintéticos, sin tocar BD): caso "mismo titular" → match correcto; caso "titular distinto" → mismatch detectado.

### Añadido — Nombre de archivo de certificados legible por titular

- **Problema:** `{id}_{cod_request}.p12` (ej. `1184_A152B9Q7L.p12`) dificultaba identificar a quién pertenece un archivo descargado.
- **Fix:** `{slug(company_name)}_{id}` (ej. `edward-geovanny-vasco-gallego_1184.p12`) en `AssembleP12Job` (P12+ZIP), `DownloadP7bJob` (P7B) y `RedownloadCertificateUseCase` (P12+ZIP). `Str::slug()` quita acentos/ñ/puntos automáticamente. Fallback al formato anterior si `company_name` viene vacío. Los archivos ya existentes no se renombran (evita romper referencias en BD).

### Añadido — Viafirma: mapeo de estados remotos faltantes (manual RA actualizado 2026-08-21)

- **Riesgo encontrado:** un código remoto no reconocido por `RemoteStatus` hace que `GuzzleViafirmaClient::getStatus()` lance excepción — el job cae en el hook `failed()` y reintenta cada 30s **indefinidamente sin avanzar**, porque el estado real nunca se reconoce. El manual actualizado documenta varios códigos que no estaban mapeados.
- **Nuevos casos en `RemoteStatus`:** `collate_data`, `checking`, `docRequired`, `docUploaded` → `isStopRecoverable()` (igual que `rues_error`/`accreditation_rejected`, requieren operador RA, polling continúa cada 5 min). `Cite_To_Finish`, `processingContract` → `isReadyToDownload()` (sub-estados de `signedContract`, no bloquean la descarga del P7B).
- Mensajes descriptivos agregados en `StateMachine::buildErrorMessage()` para los 4 estados bloqueantes nuevos.
- Verificado exhaustivamente: los 20 casos de `RemoteStatus::cases()` mapean a un `InternalState` sin excepción (`toInternalState()` es un `match` sin `default`, así que cualquier caso sin categorizar habría lanzado `UnhandledMatchError` — confirmado que no ocurre).
- Documentación: tabla completa de estados agregada a `docs/runbooks/viafirma-incidents.md` §5.1.

### Corregido — Viafirma: polling se detenía solo (expiración) y perdía certificados ya generados

- **Bug principal:** `PollViafirmaStatusJob` marcaba la solicitud como `EXPIRED` (estado terminal) al superar SLA/intentos/`expires_at`, deteniendo el polling **para siempre** — incluso si Viafirma ya tenía el certificado listo (`Generated_Not_Downloaded`). Caso real: solicitud 1138 (LCCH SERVICIOS SAS), certificado generado pero nunca descargado porque el sistema dejó de consultar.
  - Reasignar manualmente `internal_state` a `POLLING` no servía: `hasExceededSla()`/`hasExceededMaxAttempts()` se recalculan en vivo en cada poll y volvían a expirarla.
- **Fix:** `PollViafirmaStatusJob::executePolling()` — eliminado el bloque que llamaba a `StateMachine::markExpired()` por tiempo/intentos. El polling ahora solo se detiene cuando Viafirma reporta un estado remoto realmente terminal.

### Corregido — Viafirma: cadena de polling moría silenciosamente ante fallos o colisión de mutex

- **Bug:** `tries = 1` + auto-reprogramación solo dentro de una ejecución exitosa → cualquier excepción no controlada cortaba la cadena de polling sin dejar rastro, dependiendo del watchdog (`ReviveStalledViafirmaPollsJob`, cada 5 min, huecos de hasta 20 min) como única red de seguridad.
- **Fix** en `PollViafirmaStatusJob`:
  - Mutex ocupado (`Cache::lock` fallido) → reprograma en 10s en vez de retornar sin más.
  - Nuevo hook `failed(Throwable $exception)` → reprograma el siguiente poll en 30s ante cualquier fallo no controlado.

### Corregido — Viafirma: link KYC (`kyc_accreditation_link`) se perdía en algunas solicitudes

- **Bug:** `StateMachine::transition()` solo capturaba el link al detectar el valor remoto bruto `accreditation`, ignorando los sub-estados documentados por el proveedor (`accreditation_check`, `accreditation_completed`, `accreditation_verified`). Si el primer poll observado ya reportaba un sub-estado, el evento `ViafirmaAccreditationReached` nunca se disparaba y el link quedaba `null` de forma permanente (no recuperable ni siquiera on-demand, ya visto en solicitudes 39 y 40).
- **Fix:** detección ampliada a toda `StateMachine::ACCREDITATION_FAMILY` (bruto + 3 sub-estados).

### Añadido — Viafirma: `viafirma_status_history` — evitar crecimiento sin control + trazabilidad de salud del polling

- **Contexto:** al eliminar la expiración automática, una solicitud puede quedar días en el mismo estado remoto; antes se insertaba una fila idéntica en cada poll (60s).
- **`StateMachine::transition()`:** nueva fila solo si `internal_state` o `remote_status` cambian; si no, se actualiza la fila vigente (`touchCurrentHistoryRow()`).
- **Columnas nuevas** en `viafirma_status_history` (migración creada, **pendiente de ejecución manual** — ver `database/migrations/viafirma/README.md`):
  - `created_at`: fijo, momento en que inicia el episodio de estado (no se actualiza tras el INSERT).
  - `poll_count_in_state`: se incrementa en cada poll que confirma el mismo estado sin cambios — permite detectar polling degradado (`occurred_at - created_at` grande con `poll_count_in_state` bajo).

### Añadido — Endpoint `GET /api/v1/certificate-request/{id}/issuance`: campos faltantes para Viafirma

- `ViafirmaIssuanceProvider::status()` ahora incluye en `data`: `kyc_accreditation_link`, `poll_attempts`, `last_error_code`, `last_error_message`.
- `mapInternalStateToStatus()` reescrito como `match` exhaustivo (sin `default`): `READY_TO_DOWNLOAD` ya no caía incorrectamente en `STATUS_PROCESSING`; `FAILED_RECOVERABLE` y `REVOKED` ahora mapean explícitamente.
- Documentación OpenAPI (`SwaggerDefinitions.php`, schema `IssuanceViafirmaData`) actualizada con los 4 campos nuevos.

### Añadido — Viafirma: correo automático a la empresa con el link KYC

- Al capturar exitosamente `kyc_accreditation_link` (`FetchKycAccreditationLinkJob`), se envía un correo a `companies.email` de la empresa dueña de la solicitud (no al suscriptor final, que ya lo recibe directo de Viafirma) — para que puedan reenviarlo a su cliente.
- El link se incluye como **texto plano copiable** además del botón de acción, ya que el propósito explícito es que la empresa lo reenvíe.
- `ViafirmaAccreditationPendingNotification` — agregado el canal `mail` (tenía un TODO pendiente desde Sprint 5). `via()` detecta `AnonymousNotifiable` (envío directo por email sin `User`) para no intentar el canal `database`.
- Fallos de envío se loguean (`viafirma.kyc_link_job.notify_failed`) pero no marcan el job como fallido — el link ya quedó persistido correctamente.

### Eliminado — Listener muerto `NotifyClientOnAccreditationListener`

- Reaccionaba a `ViafirmaStatusChanged` con `remoteStatus === ACCREDITATION` exacto — evento que casi nunca se dispara en ese punto exacto (solo se emite cuando cambia `internal_state`, no cuando solo cambia `remote_status` dentro de la familia de acreditación).
- Aunque el evento hubiera disparado, `$company->users` habría lanzado `BadMethodCallException` — `Company` no tiene esa relación definida.
- Redundante con el nuevo envío de correo (arriba): mantenerlo habría generado dos correos por el mismo motivo. Eliminado el archivo y su registro en `EventServiceProvider`.

### Añadido — Sandbox: `MockViafirmaClient` ahora simula el paso `accreditation`

- **Gap encontrado:** la progresión simulada (`rues_check → inProcess → Generated_Not_Downloaded`) nunca pasaba por ningún estado de la familia `accreditation` — por lo tanto, en sandbox, `ViafirmaAccreditationReached` nunca se disparaba y el flujo completo de captura de link KYC + correo a la empresa (ambos de esta sesión) era invisible para integradores probando en ese entorno.
- **Fix:** nueva progresión: Poll 1 → `rues_check`, Poll 2 → `accreditation`, Poll 3 → `inProcess`, Poll 4+ → `Generated_Not_Downloaded`. Requiere `CACHE_DRIVER` distinto de `array` para que el contador de polls persista entre requests.

### Corregido — Viafirma: contención de cola causaba huecos de polling de hasta 5 min

- **Causa raíz confirmada:** Supervisor de producción (`matricerts-prod-worker`) usa solo `numprocs=2` para `--queue=default,webhooks,redownload,reports,notifications`. `PollViafirmaStatusJob` cae en `default` sin cola dedicada, compartiendo pool con `ProcessCertificateJob` (`timeout=300`, OCR/IA sobre documentos). Si ambos workers quedan ocupados con jobs pesados simultáneamente, el polling de Viafirma se detiene por completo hasta 5 minutos — repetido durante horas, esto reproduce huecos promedio de ~24 min/poll. Empeora con el tiempo porque la población de solicitudes en `POLLING` ya no tiene techo (fix de expiración de esta misma sesión).
- **Fix de código:** `PollViafirmaStatusJob`, `FetchKycAccreditationLinkJob`, `DownloadP7bJob` y `AssembleP12Job` ahora usan `onQueue('viafirma-poll')` — aísla la cadena time-critical del pool compartido.
- **Pendiente en servidor (no aplicado por el asistente):** crear programa Supervisor dedicado `matricerts-prod-worker-viafirma` con `--queue=viafirma-poll --timeout=30`, `numprocs` propio. Sin este paso, el código enruta a una cola que nadie consume — **debe aplicarse antes o junto con el deploy de este código**.

### Documentación actualizada

- `docs/runbooks/viafirma-incidents.md` — corregida sección 2.2 (rol invertido operador↔cliente, URL KYC obsoleta reemplazada por `kyc_accreditation_link`), sección 2.3 (auto-reparación del polling), sección 5 (ya no hay expiración automática; documentado el flujo de correo a la empresa).
- `docs/2026-06-24-SANDBOX-ANALISIS-FLUJOS-MOCK.md` — diagrama y tabla de comportamiento del mock actualizados (paso `accreditation`, referencia al listener eliminado quitada, ya no menciona límite de "288 intentos / 8h").
- `docs/2026-07-09-implementacion-kyc-link-persistence.md` — nota aclarando que la cobertura de sub-estados descrita originalmente no estaba realmente implementada hasta el fix de hoy.

### Añadido — Tests: aislamiento forzado de base de datos

- `phpunit.xml`: `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` activado (antes comentado). Garantiza que ninguna corrida de tests pueda tocar `maticerts` (o cualquier BD real vía `.env`).
- **Hallazgo (sin corregir, pendiente de decisión):** varios tests de Viafirma (`StateMachineTest`, `PollingSchedulerTest`, `ViafirmaRequestFailedListenerTest`, etc.) no usan `RefreshDatabase` y dependían implícitamente de que `maticerts` ya tuviera las tablas creadas manualmente — contra SQLite en memoria fallan con `no such table` al no existir bootstrap de esquema para tests. Además, 5 archivos de test tienen BOM UTF-8 antes de `<?php` que rompe su carga (`GetKycLinkUseCaseTest.php`, `StateMachineAccreditationTest.php`, `FetchKycAccreditationLinkJobTest.php`, `KycLinkControllerTest.php`, `CertificateIssuanceViafirmaTest.php`).

---

## [1.11.0] - 2026-07-22

### Corregido — Cálculo de tier de precio: considerar certificados vigentes del año anterior/actual

- **Bug principal:** El endpoint `GET /api/v1/pricing?quantity=1&vigencia=1` no contaba correctamente los certificados vigentes para determinar el tier de precio de empresas con `user_type_id` 3 o 4 (Arrendamiento en Servidor / Partner)
  - Usaba `whereYear('updated_at', ...)` en lugar de `created_at` — los certificados se "movían" de año al ser modificados por scripts o jobs
  - No filtraba por `expiration_date` — certificados ya vencidos se contaban como vigentes hasta que el job diario `MarkExpiredCertificatesJob` los marcara como `EXPIRED`
  - Mezclaba dos fuentes de datos (`CertificateRequest` + `CertificateOrder`) con lógica compleja de MAX

- **Fix:** En `PricingService::resolveEffectiveQuantity()`:
  - **Vigencia ajustada por vida útil:**
    - `life=1`: vigente si `expiration_date IS NULL OR expiration_date > NOW()`
    - `life=2`: vigente si `expiration_date IS NULL OR expiration_date > NOW() + 1 año` (resta 1 año para reflejar cobertura anual efectiva)
  - **Ventana de año:** solo cuenta certificados solicitados en el año anterior o actual (`created_at`)
  - **Fórmula simple:** `effective_quantity = COUNT(certificados vigentes) + cantidad a comprar`
  - Query única con QueryBuilder optimizado para ambos tipos de vida útil

- **Tests agregados:** `tests/Unit/Services/PricingServiceTest.php` (17 casos):
  - Vigencia por estado (`PROCESSED`, `PROCESSING`, `EXPIRED`)
  - Vigencia ajustada por `life` (1 año vs 2 años)
  - Ventana de año anterior/actual
  - Empresas diferentes no interfieren
  - `user_type_id` no volumen-based ignora historial

- **Factories agregados:** `CertificateRequestFactory` y `CompanyFactory` para tests

---

## [1.10.0] - 2026-07-15

### Añadido — Job de promoción automática de certificados (flujo mail)

- **`PromoteMailCertificateRequestsJob`** — Automatiza la transición de solicitudes de certificado en el flujo "mail":
  - Se ejecuta cada 5 minutos vía scheduler
  - Busca solicitudes en estado `SENT` o `ACCEPTED` cuya empresa tiene `issuance_provider = 'mail'`
  - Encadena automáticamente: `SENT` → `ACCEPTED` → `PROCESSING` en la misma ejecución
  - Reutiliza servicios existentes sin duplicación de código:
    - `UpdateCertificateStatusHandler::handle()` para cambios de estado
    - `CertificateRequestMailService::sendMail()` para envío directo sin orquestación innecesaria
  - Evita intervención manual en el flujo mail, mejorando la experiencia del usuario
  - Logging detallado por solicitud fallida y resumen de operación

**Registrado en:** `app/Console/Kernel.php` → `certificates:auto-promote-mail` cada 5 minutos

---

## [Unreleased - Previous]

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
