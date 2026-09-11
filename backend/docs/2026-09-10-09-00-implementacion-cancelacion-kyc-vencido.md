# Implementación: Cancelación automática por vencimiento de KYC + reintegro de cupo + avisos WhatsApp

> Estado: **IMPLEMENTADO Y CON TESTS — PENDIENTE DE PRUEBA MANUAL Y DEPLOY**. Respuestas del usuario (2026-09-10): (1) sí a los dos avisos, último llamado 24h antes tal como se definió desde el mensaje original; (2) cron cada hora confirmado; (3) sí, la cuenta maestra (Casa de Software) debe recibir correo en **todos** los avisos/notificaciones, no solo WhatsApp — esto amplió el alcance, ver sección 3.1; (4) no se eliminan adjuntos.
>
> Implementado: `ExpireStalledKycAccreditationsJob`, `CancelExpiredKycRequestUseCase`, `ViafirmaKycLastCallNotification`, `ViafirmaKycExpiredNotification`, migración+DDL (no ejecutado), entrada en `Kernel.php`. 12 tests unitarios nuevos, 100% mockeados sin BD, todos pasando. No se ejecutó el job completo contra la BD local (a diferencia del webhook de la sesión anterior) porque cancela solicitudes y reintegra cupos reales — pendiente de que el usuario decida cómo/cuándo probarlo manualmente antes del deploy.
>
> **Revisión 2026-09-10 (feedback post-implementación):** el usuario detectó 2 huecos reales:
> 1. **Historial visible faltante:** `StateMachine::markExpired()` nunca disparaba `ViafirmaStatusChanged`, solo `ViafirmaRequestFailed` — sin ese evento, `ViafirmaRequestStateChangedListener::syncExpiredStatus()` (que ya existía y ya escribe en `change_histories`) nunca corría. **Corregido**: `markExpired()` ahora dispara ambos eventos; se quitó la sincronización manual duplicada de `CancelExpiredKycRequestUseCase`.
> 2. **Correo interno engañoso:** `ViafirmaRequestFailedListener` enviaba el mismo correo de "FALLIDA - ACCIÓN REQUERIDA" para cualquier causa, incluyendo `POLL_EXPIRED` — que no es un fallo real, es el cron funcionando como se diseñó. **Corregido**: mensaje/asunto distintos para `POLL_EXPIRED` (informativo, aclara que ya se canceló y el cupo ya se reintegró, sin pedir acción).
> 3. **Revocación en el proveedor: NO existe endpoint para esto.** `getRevocationCode()`/`revokeCertificate()` solo aplican a certificados que llegaron a `inProcess` o superior — las solicitudes que cancela este cron nunca pasan de `accreditation`, así que Viafirma nunca les asigna código de revocación. **El usuario va a solicitar a Viafirma un endpoint para cancelar/rechazar una solicitud en curso** — pendiente de su respuesta, ver sección 9.

## 1. Contexto

Actualmente, si un usuario final nunca completa la verificación KYC en MetaMap, la solicitud queda en `POLLING` **indefinidamente** — desde esta misma sesión eliminamos toda auto-expiración del polling (a petición explícita: *"Realmente quiero eliminar la expiración, no el polling"*). Eso resolvió el problema original (bloquear consultas al proveedor), pero dejó un vacío: **nada libera el cupo consumido** por una solicitud que el usuario final simplemente abandonó.

Se pide cerrar ese ciclo:

1. Tras el plazo ya definido (`VIAFIRMA_POLL_EXPIRATION_HOURS`, ver 1.1), **cancelar** la solicitud y **reintegrar el cupo** consumido — es una operación de inventario interno, no una devolución financiera.
2. Antes de cancelar, enviar un **aviso de último llamado** por WhatsApp (24h antes del vencimiento).
3. Al cancelar, enviar un **aviso de cancelación efectiva** por WhatsApp, confirmando que el cupo ya está disponible de nuevo.
4. Reutilizar el mismo mecanismo de webhook n8n ya implementado (`KycWebhookNotifierContract`), agregando dos valores nuevos de `tipo`: `ultimo_llamado` y `cancelacion`.

### 1.1 La variable de "7 días" ya existe — no hay que crear una nueva

Verificado en código: `expires_at` (columna en `viafirma_certificate_request_states`) se calcula una sola vez, al someter el CSR:

```php
// IssueCertificateUseCase.php:175
'expires_at' => Carbon::now()->addHours((int) config('viafirma.polling.expiration_hours', 96)),
```

Y el frontend **no tiene un valor de 7 días propio** — solo consume ese mismo `expires_at` para el countdown "Vence en Xd Xh" (`request-in-process-view.component.ts:616-628`). El diseño lee `expires_at` (ya calculado con el `.env` real, sea cual sea su valor en cada momento) en vez de volver a leer `expiration_hours` y recalcular — así el cron queda desacoplado del valor concreto de la variable: si mañana cambia a 5 días o a 10, el cron sigue funcionando igual sin tocar código.

### 1.2 Piezas que ya existen y se van a reutilizar (no se reinventa nada)

| Pieza | Ubicación | Qué hace |
|---|---|---|
| `InternalState::EXPIRED` | `app/Modules/Viafirma/Domain/Enums/InternalState.php` | Ya existe y ya mapea a `CertificateRequestStatusEnum::EXPIRED` vía `toRequestStatus()`. |
| `StateMachine::markExpired()` | `app/Modules/Viafirma/Domain/StateMachine.php:174` | Ya transiciona a `EXPIRED`, registra historial, dispara `ViafirmaRequestFailed`. **Actualmente sin ninguna llamada activa** en el código (quedó huérfano tras eliminar la auto-expiración del polling). |
| `ViafirmaRequestFailedListener` | `app/Modules/Viafirma/Application/Listeners/` | Ya escucha `ViafirmaRequestFailed` — loguea y notifica por **correo al operador RA interno** (`MAIL_SUPPORT_ADDRESS`). No toca cupos ni notifica a la Casa de Software. |
| `QuotaService::releaseQuotaForRequest(int $companyId): bool` | `app/Services/QuotaService.php` | Ya existe, atómico (`lockForUpdate`). Libera PREPAID primero, luego POSTPAID — mismo método que usa `DeleteCertificateRequestHandler` al eliminar una solicitud manualmente. |
| `KycWebhookNotifierContract` / `N8nKycWebhookClient` | `app/Modules/Viafirma/*` | Ya implementado y verificado en producción (sesión anterior). Solo hay que agregar 2 valores nuevos de `tipo`. |
| `CertificateRequest::applicantDisplayName()` | `app/Models/CertificateRequest.php` | Ya existe (sesión anterior) — nombre del solicitante para el mensaje de WhatsApp. |

## 2. Decisión de diseño: NO se borra nada, se marca como `EXPIRED`

`DeleteCertificateRequestHandler` borra archivos y filas — es una acción manual, deliberada, ejecutada por una persona. **Un cron automático nunca debe hacer eso.** El diseño correcto es:

- `viafirma_certificate_requests.state.internal_state` → `EXPIRED` (vía `StateMachine::markExpired()`, ya existe).
- `certificate_requests.request_status` → `EXPIRED` (sincronización manual explícita, mismo patrón que usa `AssembleP12Job.php:307` para `COMPLETED`; no es automática entre las dos tablas).
- Los archivos adjuntos, CSR, historial, todo permanece intacto para trazabilidad — igual que cualquier otro caso `FAILED`/`EXPIRED` hoy.
- El cupo se reintegra, pero el registro de la solicitud **queda como evidencia** de que existió y se canceló por vencimiento.

## 3. Contrato del webhook — 2 `tipo` nuevos

Mismo payload ya implementado (`{casa_software, whatsapp, solicitudes: [{codigo, enlace, nombre}], count, tipo}`), agregando:

- **`ultimo_llamado`**: se envía **una sola vez**, 24h antes de `expires_at`. El `enlace` sigue siendo válido (la solicitud aún no se canceló) — el mensaje invita a completar el KYC ya mismo.
- **`cancelacion`**: se envía **una sola vez**, en el momento en que el cron efectivamente cancela. El `enlace` ya no sirve (la solicitud está `EXPIRED`) — n8n decide si lo omite en el mensaje o no, pero **de nuestro lado igual lo enviamos** en la misma estructura por consistencia del contrato (más simple de mantener que tener un shape distinto por `tipo`).

### 3.1 Correo a la cuenta maestra — mismo criterio en ambos avisos nuevos

Confirmado: la Casa de Software debe recibir correo en **todas** las notificaciones, no solo WhatsApp — mismo patrón que ya existe hoy para el envío inmediato y el recordatorio diario (`ViafirmaAccreditationPendingNotification` se envía por `mail` + WhatsApp en ambos). Se agregan dos notificaciones nuevas, siguiendo la misma estructura:

- **`ViafirmaKycLastCallNotification`** — correo de último llamado (mismo momento que el WhatsApp `ultimo_llamado`), tono de urgencia, incluye el enlace (aún válido).
- **`ViafirmaKycExpiredNotification`** — correo de cancelación (mismo momento que el WhatsApp `cancelacion`), confirma que el cupo fue reintegrado y ya está disponible para una nueva solicitud. No incluye enlace (ya no sirve).

Ambas se envían vía `Notification::route('mail', $company->email)->notify(...)`, igual que `notifyMasterCompany()` en `FetchKycAccreditationLinkJob`. Se agrupan por solicitud individual (un correo por código, igual que hoy), mientras que el WhatsApp se agrupa por empresa — son canales independientes con su propia granularidad, ya establecida así en el diseño anterior.

## 4. Diseño de integración

### 4.1 Nueva columna: marca de "último llamado ya enviado"

Como el cron va a correr con más frecuencia que 1 vez al día (ver 4.3), se necesita una bandera para no reenviar el aviso de último llamado en cada ejecución dentro de la misma ventana de 24h:

```sql
ALTER TABLE viafirma_certificate_request_states
    ADD COLUMN kyc_last_call_sent_at DATETIME NULL AFTER kyc_flow_completed_user_agent;
```

(Migración creada pero **no ejecutada**, + DDL manual para producción — misma política ya seguida toda la sesión.)

### 4.2 Nuevo job: `ExpireStalledKycAccreditationsJob`

Corre en una sola pasada dos responsabilidades relacionadas (comparten el mismo criterio base "pendiente de KYC", ver `ResendPendingKycAccreditationNotificationsJob` ya existente):

**Paso A — Último llamado** (24h antes de vencer, aviso no enviado aún):
```php
ViafirmaCertificateRequest::query()
    ->whereHas('state', fn ($q) => $q
        ->where('internal_state', InternalState::POLLING->value)
        ->whereNotNull('kyc_accreditation_link')
        ->whereNull('kyc_flow_completed_at')
        ->whereNull('kyc_last_call_sent_at')
        ->whereNotNull('expires_at')
        ->whereBetween('expires_at', [now(), now()->addHours(24)])
    )
    ->with(['state', 'certificateRequest.company'])
    ->get();
```
Por cada una: enviar `ViafirmaKycLastCallNotification` por correo a la empresa, agrupar por empresa para el webhook `tipo: ultimo_llamado`, marcar `kyc_last_call_sent_at = now()`.

**Paso B — Cancelación efectiva** (ya vencidas, aún pendientes):
```php
ViafirmaCertificateRequest::query()
    ->whereHas('state', fn ($q) => $q
        ->where('internal_state', InternalState::POLLING->value)
        ->whereNotNull('kyc_accreditation_link')
        ->whereNull('kyc_flow_completed_at')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
    )
    ->with(['state', 'certificateRequest.company'])
    ->get();
```
**Nota deliberada:** la condición es `expires_at <= now()`, no una comparación de igualdad exacta contra el instante del vencimiento. Esto es intencional — para cuando el job corra, seguramente ya existan solicitudes vencidas desde antes (el cron pudo no haber corrido exactamente en el segundo del vencimiento, o quedó una acumulada de una corrida anterior que falló). Con `<=` el job recoge **todas** las vencidas acumuladas en cada pasada, sin importar hace cuánto vencieron — no solo las que cruzan el umbral justo en esa hora.

Por cada una (dentro de una transacción, con el mismo mutex de `Cache::lock()` que ya usa `PollViafirmaStatusJob` para evitar carrera con un poll concurrente que justo esté procesando la misma solicitud):

1. `$stateMachine->markExpired($entity)`.
2. `$certificateRequest->request_status = InternalState::EXPIRED->toRequestStatus()->value; $certificateRequest->save();`
3. `$quotaService->releaseQuotaForRequest($companyId)`.
4. Enviar `ViafirmaKycExpiredNotification` por correo a la empresa (una por solicitud cancelada).
5. Acumular `{codigo, enlace, nombre}` para el webhook agrupado de esa empresa.

Al final del recorrido por empresa: `webhookNotifier->notify(..., tipo: 'cancelacion')`.

### 4.3 Programación (Kernel)

Se propone **cada hora** (no diario) — para que el aviso de "último llamado" no se dispare con hasta 23h de retraso si solo corriera una vez al día:

```php
$schedule->job(new ExpireStalledKycAccreditationsJob())
    ->hourly()
    ->timezone('America/Bogota')
    ->name('viafirma:kyc-expire-and-notify')
    ->withoutOverlapping(55)
    ->onOneServer()
    ->emailOutputOnFailure(env('MAIL_SUPPORT_ADDRESS', config('mail.from.address')))
    ->appendOutputTo(storage_path('logs/scheduled-viafirma-kyc-expire.log'));
```
Cola: `notifications`.

### 4.4 Ajuste al nodo "Armar mensaje" de n8n

Los dos bloques `else if` compartidos por el usuario en el mensaje original ya son el ajuste correcto de n8n — no requieren cambios de nuestro lado, solo que el payload les llegue con la estructura ya vigente (`solicitudes[].nombre` ya incluido desde la sesión anterior).

## 5. Riesgos / consideraciones (heredadas de la conversación previa)

- **T&C de MATICERTS:** la regla "N días para verificar o se cancela y se reintegra el cupo" debe quedar documentada en los términos del servicio — pendiente, no técnico.
- **Certificado ya pagado (Wompi) + cancelación por inacción:** el cupo se reintegra, pero el dinero ya fue cobrado. Si el cliente reclama el dinero (no el cupo) después de cancelado, es un caso de atención al cliente/Estatuto del Consumidor que este cron no resuelve — fuera de alcance técnico.
- **Concurrencia con el polling activo:** si `PollViafirmaStatusJob` está procesando la misma solicitud justo cuando el cron de expiración corre (ej. el usuario completa el KYC en el segundo exacto del corte), el mutex por `codRequest` evita una doble escritura — a implementar igual que el mutex ya usado en el polling.

## 6. Preguntas resueltas (2026-09-10)

1. **Los dos avisos, confirmados.** Último llamado 24h antes de `expires_at`, tal como se definió en el mensaje original.
2. **Frecuencia del cron: cada hora, confirmado.**
3. **Correo a la cuenta maestra: sí, en todos los avisos/notificaciones** — no solo cancelación, también último llamado. Ver sección 3.1.
4. **Adjuntos: no se eliminan.** Se mantiene igual que cualquier otro caso `FAILED`/`EXPIRED` hoy.

## 7. Impacto en código (archivos a crear/modificar)

| Archivo | Cambio |
|---|---|
| `database/migrations/viafirma/2026_09_10_..._add_kyc_last_call_sent_at.php` | Nueva migración (no ejecutada) |
| `database/migrations/viafirma/2026_09_10_..._add_kyc_last_call_sent_at.sql` | DDL manual para producción |
| `app/Modules/Viafirma/Infrastructure/Persistence/Models/ViafirmaCertificateRequestState.php` | + `kyc_last_call_sent_at` en `$fillable`/`$casts` |
| `app/Modules/Viafirma/Infrastructure/Jobs/ExpireStalledKycAccreditationsJob.php` | Nuevo — job del cron horario (pasos A y B) |
| `app/Modules/Viafirma/Application/Notifications/ViafirmaKycLastCallNotification.php` | Nueva — correo de último llamado |
| `app/Modules/Viafirma/Application/Notifications/ViafirmaKycExpiredNotification.php` | Nueva — correo de cancelación |
| `app/Console/Kernel.php` | + entrada de scheduler horaria |
| Tests nuevos (mockeados, sin BD) | `ExpireStalledKycAccreditationsJob`, ambas notificaciones |
| `app/Modules/Viafirma/Domain/StateMachine.php` | `markExpired()` ahora también dispara `ViafirmaStatusChanged` (faltaba — sin él, `ViafirmaRequestStateChangedListener::syncExpiredStatus()` nunca sincronizaba `change_histories`) |
| `app/Modules/Viafirma/Application/UseCases/CancelExpiredKycRequestUseCase.php` | Quitada la sincronización manual duplicada de `request_status` — ahora la hace el listener vía el evento |
| `app/Modules/Viafirma/Application/Listeners/ViafirmaRequestFailedListener.php` | Correo distinto (informativo, sin "ACCIÓN REQUERIDA") cuando `error_code === POLL_EXPIRED` |
| `tests/Unit/Modules/Viafirma/Domain/StateMachineMarkExpiredTest.php` | Nuevo — cubre el guard clause de `markExpired()`; el camino feliz no es mockeable sin BD (ver nota en el archivo) |

## 8. Plan de rollout

1. ~~Confirmar respuestas de la sección 6~~ ✅ hecho (2026-09-10).
2. Crear migración (no ejecutada) + DDL manual para `kyc_last_call_sent_at` — **entregado en este mismo turno**, ver `database/migrations/viafirma/`.
3. Implementar `ExpireStalledKycAccreditationsJob` + las 2 notificaciones nuevas, con tests unitarios mockeados (sin BD) — mismo patrón usado para `ResendPendingKycAccreditationNotificationsJob`.
4. Agregar `ultimo_llamado`/`cancelacion` como valores válidos de `tipo` en la documentación del contrato (no requiere cambio de código en `N8nKycWebhookClient`, que ya acepta `tipo` como string libre).
5. Agregar la entrada al scheduler en `Kernel.php`.
6. Probar en local con una solicitud real (mismo procedimiento manual vía tinker usado para validar el webhook la sesión anterior) antes de desplegar.
7. Documentar en `CHANGELOG.md`.

## 9. Pendiente — endpoint de cancelación en el proveedor (bloqueante para el punto 1 de la sección 1)

**No existe en nuestro contrato actual ningún endpoint de Viafirma para cancelar/rechazar una solicitud que nunca llegó a `inProcess`.** `getRevocationCode()`/`revokeCertificate()` solo aplican a certificados ya emitidos o en proceso avanzado — las solicitudes que cancela este cron se quedan atascadas en `accreditation`, sin código de revocación asignado nunca.

El usuario va a solicitar a Viafirma (Benito/Cesar, mismo canal usado para `advancedStatus`) un endpoint para cancelar una solicitud en curso desde nuestro lado. **Una vez confirmado ese endpoint**, agregar:

- Método nuevo en `ViafirmaClient` (ej. `cancelRequest(string $codRequest): void`).
- Llamada desde `CancelExpiredKycRequestUseCase::handle()`, después de `markExpired()` y antes/después de `releaseQuotaForRequest()` (a definir orden según qué tan crítico sea que la cancelación remota sea atómica con el reintegro del cupo).
- Manejo de fallo: si Viafirma rechaza la cancelación (ya fue procesada de su lado, códigos inválidos, etc.), decidir si igual se reintegra el cupo localmente o se deja pendiente para revisión manual.
