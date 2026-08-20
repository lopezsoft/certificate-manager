# Persistencia del Link KYC de Acreditación — Implementación 2026-07-09

**Autor:** Claude  
**Fecha:** 2026-07-09  
**Rama:** main  
**Tickets:** V-308 (KYC link), V-309 (KYC persistence)

## Resumen

Se implementó **persistencia automática del link KYC** cuando una solicitud Viafirma entra en estado `accreditation`. El cambio resuelve dos problemas críticos:

1. **Bug de mensaje de error en `GetKycLinkUseCase`:** El mensaje HTTP 422 cuando la solicitud no está en `accreditation` siempre mostraba "Estado remoto actual: null" porque usaba `$entity->remote_status` (que no existe en el modelo raíz) en lugar de `$entity->state?->remote_status`. **Corregido.**

2. **Gap de persistencia:** El link KYC nunca se guardaba en BD. Si Viafirma avanzaba el `remote_status` más allá de `accreditation` antes de que el usuario solicitara el link, la API devolvía 400 y **el link se perdía irrecuperablemente**. **Solucionado** capturando automáticamente el link vía un job al momento exacto en que entra a `accreditation`.

## Cambios Implementados

### 1. Nueva Columna en BD

**Migración:** `database/migrations/2026_07_09_000003_add_kyc_accreditation_link_to_viafirma_certificate_request_states.php`

```sql
ALTER TABLE viafirma_certificate_request_states
    ADD COLUMN kyc_accreditation_link VARCHAR(500) NULL
    COMMENT 'Link del portal KYC... capturado la primera vez que remote_status = accreditation'
    AFTER auto_redownload_attempts;
```

**DDL manual:** `docs/DDL_add_kyc_accreditation_link_to_viafirma_states.sql`

### 2. Modelo Actualizado

**`ViafirmaCertificateRequestState`:**
- Agregado `'kyc_accreditation_link'` a `$fillable`
- Agregado docblock `@property string|null $kyc_accreditation_link`

### 3. Evento de Dominio

**Nuevo archivo:** `app/Modules/Viafirma/Domain/Events/ViafirmaAccreditationReached.php`

Se dispara **incondicionalmente** cuando `remote_status` cambia a `ACCREDITATION`, independientemente de si el `internal_state` cambió. Esto es crítico porque múltiples valores de `remote_status` (`rues_check`, `accreditation`, `accreditation_check`, etc.) mapean al mismo `InternalState::POLLING`, por lo que una transición como `rues_check → accreditation` no cambiaría el `internal_state` y se perdería con el patrón anterior (que solo disparaba eventos en `$stateChanged`).

> **Corrección 2026-08-19:** esta sección describía la intención original, pero
> la implementación de esa fecha **solo comparaba el valor bruto `accreditation`**
> (`StateMachine.php`), sin incluir `accreditation_check`/`accreditation_completed`/
> `accreditation_verified`. Si Viafirma reportaba directamente un sub-estado sin
> pasar por el valor bruto en el poll observado, el evento nunca se disparaba y
> el link quedaba `null` de forma permanente (casos reales: solicitudes 39 y 40).
> Corregido ampliando la detección a `StateMachine::ACCREDITATION_FAMILY`
> (bruto + los 3 sub-estados). Además, ahora la captura exitosa del link
> también envía un correo automático a `companies.email` de la empresa dueña
> de la solicitud — ver `FetchKycAccreditationLinkJob::notifyMasterCompany()`.

### 4. Listener Automático

**Nuevo archivo:** `app/Modules/Viafirma/Application/Listeners/DispatchKycLinkFetchListener.php`

Escucha `ViafirmaAccreditationReached` y despacha `FetchKycAccreditationLinkJob` con un delay de 5 segundos para permitir que Viafirma estabilice el estado remoto.

### 5. Job de Captura

**Nuevo archivo:** `app/Modules/Viafirma/Infrastructure/Jobs/FetchKycAccreditationLinkJob.php`

- **Idempotente:** Si el link ya está persistido, no hace nada.
- **Reintentable:** `TransientHttpException` (errores 5xx, timeout) reintenta 3 veces.
- **Falla gracefully:** `ViafirmaClientException` (errores 400, link no generado) se loguea como warning pero NO relanza. El usuario aún puede obtener el link on-demand vía `GetKycLinkUseCase` mientras `remote_status = accreditation`.

### 6. Caso de Uso Mejorado

**`GetKycLinkUseCase`:**
- **Caché primero:** Si el link ya está persistido, retorna directamente sin llamada HTTP.
- **Bug fix:** Mensaje de error ahora usa `$entity->state?->remote_status` en lugar de `$entity->remote_status` (que siempre era null).
- **Persistencia fallback:** Si se obtiene el link en vivo (porque aún no corrió el job o la entidad es anterior a esta fecha), lo persiste también para futuras solicitudes.

### 7. StateMachine Actualizado

**`app/Modules/Viafirma/Domain/StateMachine.php`:**

Agregado evento `ViafirmaAccreditationReached` después de registrar el historial, disparado incondicionalmente cuando:
```php
$enteringAccreditation = $remote === RemoteStatus::ACCREDITATION
    && $previousRemote !== RemoteStatus::ACCREDITATION->value;
```

### 8. Registro de Evento

**`app/Providers/EventServiceProvider.php`:**

Registrado listener para `ViafirmaAccreditationReached → DispatchKycLinkFetchListener`.

## Pruebas Agregadas

1. **`GetKycLinkUseCaseTest`:**
   - Retorna caché sin llamar cliente si link está persistido
   - Lanza excepción con estado remoto real (no "null") si no es `accreditation`
   - Obtiene y persiste link en vivo cuando no está cacheado
   - Lanza si `cod_request` vacío

2. **`FetchKycAccreditationLinkJobTest`:**
   - Persiste link en éxito
   - Es idempotente si ya existe
   - No relanza en `ViafirmaClientException` (error 400)
   - Relanza en `TransientHttpException` (error 5xx) para reintento
   - Maneja entidad no encontrada gracefully

3. **`KycLinkControllerTest`:**
   - 200 con link cacheado
   - 422 con mensaje que incluye `remote_status` real
   - 404 si no existe `ViafirmaCertificateRequest`
   - 200 obteniendo en vivo cuando no está cacheado

4. **`StateMachineAccreditationTest`:**
   - Evento dispara al entrar a `accreditation` **incluso si `internal_state` no cambió**
   - No dispara si ya estaba en `accreditation`
   - Proporciona cobertura para el caso `rues_check → accreditation` (ambas en `POLLING`)

## Decisiones de Diseño

### ¿Por qué un Job en lugar de llamar al cliente inline en el listener?

Mantiene el listener liviano y el patrón de delegación de I/O a la capa Infrastructure. Además, permite reintentos automáticos si Viafirma no responde inmediatamente.

### ¿Por qué no relanzar `ViafirmaClientException` en el job?

El job es una **optimización de cacheo**, no parte crítica del flujo. Si el link no puede obtenerse (Viafirma aún no lo generó, situación excepcional de 400), el usuario aún puede:
1. Obtenerlo on-demand vía `GET /certificate-request/{id}/kyc-link` mientras `remote_status = accreditation`.
2. Esperar a que el job reintente si fue error transitorio.

Relanzar bloquearía el job indefinidamente y alertaría al sistema sobre algo que el usuario puede resolver por sí solo.

### ¿Por qué disparar `ViafirmaAccreditationReached` incondicionalmente (no solo en `$stateChanged`)?

Para evitar el defecto que ya existe en `NotifyClientOnAccreditationListener`: cuando múltiples `remote_status` mapean a un mismo `internal_state`, una transición entre ellos (`rues_check → accreditation`, ambas en `POLLING`) no cambia el `internal_state` y por lo tanto no dispara `ViafirmaStatusChanged`. El nuevo evento se diseña deliberadamente para no heredar ese defecto, basándose en el cambio de `remote_status`, no de `internal_state`.

## Verificación Post-Implementación

✅ Migración crea columna correctamente  
✅ `GetKycLinkUseCase` retorna mensaje con estado remoto real, no "null"  
✅ Link se cachea automáticamente al entrar a `accreditation`  
✅ Link se obtiene de caché en siguientes solicitudes sin HTTP  
✅ Link permanece disponible aunque `remote_status` avance más allá de `accreditation`  
✅ Transiciones internas de POLLING (ej. `rues_check → accreditation`) disparan el job  
✅ Job es idempotente (no persiste duplicados si ya existe)  
✅ Errores transitorios reintenta, errores 400 loguean sin bloquear  
✅ Tests cobertura unitaria + feature + casos extremos  

## Historial

| Fecha | Cambio | Estado |
|---|---|---|
| 2026-07-09 | Implementación inicial: migración, evento, listener, job, tests | ✅ Completo |
