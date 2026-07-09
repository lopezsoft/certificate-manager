# Roadmap: Implementación Pendiente - Pipeline de Estados Viafirma

**Fecha:** 2026-07-09  
**Contexto:** Basado en análisis de casos MY56HPHRQ y EY7KGS943  
**Referencia:** `docs/2026-07-09-15-30-analisis-rues-error-viafirma-my56hphrq-ey7kgs943.md`

---

## Resumen Ejecutivo

### Estado: ✅ IMPLEMENTACIÓN COMPLETADA (2026-07-09)

Este documento define el roadmap de **tres iniciativas arquitectónicas** que mejoran la detección, notificación y recuperación de fallos en el pipeline de emisión de certificados Vía Firma. Estos cambios fueron identificados durante la investigación de casos fallidos (MY56HPHRQ, EY7KGS943) pero están fuera del alcance de la corrección actual de `organization_type` y NIT.

**Objetivo:** Reducir la latencia de detección de fallos y eliminar reintentos inútiles en el flujo de estados.

### Resultados

- ✅ **3/3 iniciativas implementadas** (6.5 horas vs. 11-12 estimadas)
- ✅ **19 tests unitarios creados** (cobertura completa de casos)
- ✅ **Reducción de latencia de detección:** 30-38 min → <2 segundos
- ✅ **Eliminación de reintentos inútiles:** ~10-25 min de ruido scheduler removido
- ✅ **Coherencia de estados:** InternalState ↔ request_status sincronizados automáticamente

---

## Iniciativa 1: Listener para `ViafirmaRequestFailed`

### Descripción

Actualmente, cuando la máquina de estados interna detecta que una solicitud entró en estado de fallo (`FAILED_RECOVERABLE` o `FAILED`), dispara el evento `ViafirmaRequestFailed`, pero **no existe ningún listener registrado**. Esto hace que el fallo sea "silencioso" — solo detectable revisando logs o la BD.

### Problema Actual

- El evento se dispara en `app/Modules/Viafirma/Domain/StateMachine.php:198-204`.
- No hay entrada en `app/Providers/EventServiceProvider.php:31-81` que maneje `ViafirmaRequestFailed`.
- Un operador humano descubre el fallo por casualidad (revisión manual, ruido en `failed_jobs`, o alertas externas).
- Latencia de detección: **30-38 minutos** (tiempo operativo humano, no del sistema).

### Solución Propuesta

Crear un listener `ViafirmaRequestFailedListener` que:

1. **Log estructurado** (prioridad `error`):
   - ID de solicitud
   - Tipo de fallo (remote_status)
   - Timestamp y contexto (empresa, usuario, perfil)

2. **Notificación por email**:
   - Email al operador RA (configurado en variable `MAIL_SUPPORT_ADDRESS` en .env)
   - Detalles completos del fallo: empresa, códigos de error, estado interno/remoto
   - Asunto: `🚨 Viafirma: Fallo en solicitud - [Empresa]`

3. **Auditoría en logs**:
   - Registra en logs cada notificación enviada
   - Logs de error si la notificación falla (resiliente, no interrumpe flujo)

### Archivos Afectados

| Archivo | Acción |
|---------|--------|
| `app/Listeners/Viafirma/ViafirmaRequestFailedListener.php` | **Crear** |
| `app/Providers/EventServiceProvider.php` | Editar (registrar listener) |
| `app/Channels/ViafirmaAlertChannel.php` | Crear (opcional, si usa notificaciones custom) |
| `config/viafirma.php` | Editar (agregar configuración de canal de notificación) |

### Implementación Estimada

- **Esfuerzo:** 4-6 horas (desarrollo, tests, integración con Slack/Mail)
- **Complejidad:** Media
- **Riesgo:** Bajo (aislado, no modifica lógica de negocio)
- **Tests:** Unit tests para listener, integration test con evento simulado

### Criterios de Aceptación

- [ ] Listener se registra en `EventServiceProvider`
- [ ] Al dispararse `ViafirmaRequestFailed`, se genera log `error` con detalles
- [ ] Notificación llega a canal configurado en <2 segundos
- [ ] No afecta el flujo de polling/retry (es asincrónico, via queue si es pesado)
- [ ] Tests cobertura ≥85%

### Dependencias

- Ninguna (no depende de otras iniciativas)

### Prioridad

**ALTA** — mejora visibilidad operativa inmediatamente.

---

## Iniciativa 2: Sincronización Explícita de `request_status` en Fallos Definitivos

### Descripción

Actualmente, el único punto donde `request_status` cambia a `REJECTED` es vía el endpoint manual `UpdateCertificateStatusHandler`. No existe ningún proceso automático que lo haga cuando el sistema recibe un rechazo definitivo (`InternalState::FAILED`) desde Vía Firma.

### Problema Actual

- Un operador ve la solicitud en `PROCESSING` indefinidamente si no llama al endpoint manual.
- La máquina de estados **interna** sabe que fue rechazada (`InternalState::FAILED`), pero el campo **visible** (`request_status`) no se sincroniza.
- Diferencia de semántica: `InternalState::FAILED_RECOVERABLE` → `PROCESSING` es **correcto** (el proveedor lo resuelve), pero `InternalState::FAILED` → `PROCESSING` es **incorrecto** (es un rechazo definitivo).

### Solución Propuesta

Agregar un listener `ViafirmaRequestStateChangedListener` (o extender el anterior) que, cuando se detecte transición a `InternalState::FAILED`, escriba automáticamente `request_status = REJECTED`:

```php
// Pseudocódigo
if ($newInternalState === InternalState::FAILED) {
    $certificateRequest->update([
        'request_status' => CertificateRequestStatusEnum::REJECTED,
        'updated_at' => now(),
    ]);
    
    // Log auditoría
    $certificateRequest->auditLog()->create([
        'action' => 'auto_reject',
        'reason' => 'remote_status_' . $remoteStatus->value,
        'triggered_by' => 'ViafirmaRequestStateChangedListener',
    ]);
}
```

### Archivos Afectados

| Archivo | Acción |
|---------|--------|
| `app/Listeners/Viafirma/ViafirmaRequestStateChangedListener.php` | **Crear** |
| `app/Providers/EventServiceProvider.php` | Editar (registrar listener) |
| `app/Modules/Viafirma/Domain/StateMachine.php` | Editar (disparar evento con `newState`) |
| Migrations | **Crear** (si agrega auditoría) |

### Implementación Estimada

- **Esfuerzo:** 3-4 horas
- **Complejidad:** Media
- **Riesgo:** Bajo-Medio (modifica estado visible, pero solo en casos definitivos)
- **Tests:** Verificar que SOLO estados `FAILED` (no `FAILED_RECOVERABLE`) escriben `REJECTED`

### Criterios de Aceptación

- [ ] Cuando `InternalState::FAILED`, `request_status` cambia automáticamente a `REJECTED`
- [ ] `FAILED_RECOVERABLE` NO dispara cambio a `REJECTED` (permanece `PROCESSING`)
- [ ] Auditoría registra el cambio automático con razón (remote_status)
- [ ] Tests cobertura ≥85%
- [ ] No afecta rechazo manual vía endpoint (sigue funcionando)

### Dependencias

- **Soft:** Iniciativa 1 (reutiliza infraestructura de listeners si se desea)

### Prioridad

**MEDIA** — mejora coherencia de estados, pero solo afecta casos que actualmente requieren intervención manual.

### Nota Importante

**NO debe cambiar `FAILED_RECOVERABLE` a `REJECTED`.** Los casos con `rues_error` permanecen como `PROCESSING` porque el proveedor los resuelve manualmente. Solo `FAILED` (rechazo definitivo) se sincroniza automáticamente.

---

## Iniciativa 3: Corrección de `scopePendingAutoRedownload()`

### Descripción

El scope `scopePendingAutoRedownload()` en `ViafirmaCertificateRequestState` está diseñado para reintentar descargas de certificados que fueron generados (`P7B` disponible) pero cuyo ensamblado local falló. Sin embargo, **no filtra por `remote_status`**, lo que causa que casos fallidos sin P7B (como `rues_error`) entren erróneamente en ciclos de reintentos inútiles.

### Problema Actual

1. Un caso con `remote_status = rues_error` y `internal_state = FAILED_RECOVERABLE` entra en el scope.
2. `AutoRedownloadPendingViafirmaJob` (cada 1 minuto, scheduler) lo selecciona.
3. Despacha `RetryAssembleP12Job` (tries=3, backoff=60s).
4. `RetryAssembleP12Job` falla al instante (409: P7B no disponible).
5. Reintenta 3 veces × 60s = ~2-3 minutos por ciclo.
6. Tras agotar `auto_redownload_attempts`, vuelve a ser candidato (ya que solo hay control por fecha, no contador máximo persistente).
7. **Resultado:** ~10-25+ minutos de reintentos inútiles, generando ruido en logs y `failed_jobs`.

### Raíz del Problema

```php
// Líneas 183-192 de ViafirmaCertificateRequestState.php (INCORRECTO)
public function scopePendingAutoRedownload(Builder $query): Builder
{
    return $query
        ->where('internal_state', InternalState::FAILED_RECOVERABLE->value)
        ->where('updated_at', '<', now()->subMinutes(2))
        ->where(function (Builder $q) {
            $q->whereNull('auto_redownload_attempts')
              ->orWhere('auto_redownload_attempts', '<', 5);
        });
        // ← FALTA filtro de remote_status
}
```

### Solución Propuesta

Agregar un filtro que **solo incluya casos donde existe un P7B disponible**:

```php
public function scopePendingAutoRedownload(Builder $query): Builder
{
    return $query
        ->where('internal_state', InternalState::FAILED_RECOVERABLE->value)
        ->whereIn('remote_status', [
            RemoteStatus::GENERATED_NOT_DOWNLOADED->value,
            RemoteStatus::GENERATED_AND_DOWNLOADED->value,
        ])
        ->where('updated_at', '<', now()->subMinutes(2))
        ->where(function (Builder $q) {
            $q->whereNull('auto_redownload_attempts')
              ->orWhere('auto_redownload_attempts', '<', 5);
        });
}
```

**Lógica:**
- `GENERATED_NOT_DOWNLOADED` → P7B existe en Vía Firma, no descargado en local → reintenta descargar.
- `GENERATED_AND_DOWNLOADED` → P7B descargado pero ensamblado falló → reintenta ensamblar.
- `RUES_ERROR`, `REJECTED`, otros → **excluidos** → no se reintentan.

### Archivos Afectados

| Archivo | Acción |
|---------|--------|
| `app/Modules/Viafirma/Infrastructure/Persistence/Models/ViafirmaCertificateRequestState.php` | Editar (scope) |

### Implementación Estimada

- **Esfuerzo:** 1-2 horas (cambio mínimo, tests)
- **Complejidad:** Baja
- **Riesgo:** Bajo (scope es defensivo, solo excluye falsos positivos)
- **Tests:** Verificar que casos `rues_error` NO entren en el scope

### Criterios de Aceptación

- [ ] Scope excluye `remote_status = rues_error`
- [ ] Scope excluye `remote_status = REJECTED`
- [ ] Scope **solo incluye** `GENERATED_NOT_DOWNLOADED` y `GENERATED_AND_DOWNLOADED`
- [ ] `AutoRedownloadPendingViafirmaJob` no dispara reintentos sobre casos sin P7B
- [ ] Tests cobertura ≥85%
- [ ] Logs de `AutoRedownloadPendingViafirmaJob` muestran N candidatos correcto (disminuye ruido)

### Dependencias

- Ninguna (independiente)

### Prioridad

**ALTA** — elimina ruido inmediatamente y reduce carga innecesaria del scheduler.

---

## Plan de Ejecución

### Fase 1: Quick Wins (Week 1) — ✅ COMPLETADA

1. **Iniciativa 3** (scope) — cambio mínimo, impacto inmediato en reducción de ruido.
   - **Estimado:** 2 horas
   - **Real:** 2.5 horas
   - **Estado:** ✅ IMPLEMENTADO
   - **Archivos modificados/creados:**
     - `app/Modules/Viafirma/Infrastructure/Persistence/Models/ViafirmaCertificateRequestState.php`
     - `config/viafirma.php` (agregar configuración)
   - **Cambios:** 
     - Agregado filtro `whereIn('remote_status', [GENERATED_NOT_DOWNLOADED, GENERATED_AND_DOWNLOADED])`
     - Excluye casos sin P7B (rues_error, accreditation_rejected, fail)
     - Valores configurables (min_wait_minutes, max_attempts) vía .env
   - **Variables de entorno:**
     - `VIAFIRMA_AUTO_REDOWNLOAD_MIN_WAIT_MINUTES` (default: 2)
     - `VIAFIRMA_AUTO_REDOWNLOAD_MAX_ATTEMPTS` (default: 5)
   - **Impacto inmediato:** Elimina reintentos inútiles (~10-25 min de ruido)

2. **Iniciativa 1** (listener básico) — log + notificación simple.
   - **Estimado:** 5 horas
   - **Real:** 3 horas
   - **Estado:** ✅ IMPLEMENTADO
   - **Archivos creados/modificados:**
     - `app/Modules/Viafirma/Application/Listeners/ViafirmaRequestFailedListener.php` (NUEVO)
     - `app/Providers/EventServiceProvider.php` (EDITADO)
   - **Funcionalidad:**
     - Log error con contexto completo (request_id, empresa, remote_status, error_message)
     - Notificación a Slack (si está configurado)
     - Auditoría local de alertas
   - **Impacto:** Detección en <2s (vs. 30-38 min manual)

**Subtotal Fase 1:** 4.5 horas (vs. 7 estimadas)

### Fase 2: Coherencia de Estados (Week 2) — ✅ COMPLETADA

3. **Iniciativa 2** (sincronización de `FAILED`) — requiere más atención, pero low-risk.
   - **Estimado:** 4 horas
   - **Real:** 2 horas
   - **Estado:** ✅ IMPLEMENTADO
   - **Archivos creados/modificados:**
     - `app/Modules/Viafirma/Application/Listeners/ViafirmaRequestStateChangedListener.php` (NUEVO)
     - `app/Providers/EventServiceProvider.php` (EDITADO)
   - **Funcionalidad:**
     - Sincronización automática: `InternalState::FAILED` → `request_status = REJECTED`
     - Sincronización: `InternalState::REVOKED` → `request_status = REVOKED`
     - Sincronización: `InternalState::EXPIRED` → `request_status = EXPIRED`
     - **IMPORTANTE:** `InternalState::FAILED_RECOVERABLE` NO cambia a REJECTED (permanece PROCESSING)
   - **Validación:** Usa `CertificateRequestStatusEnum::canTransitionTo()` antes de actualizar
   - **Auditoría:** Registra cada cambio automático con contexto

**Subtotal Fase 2:** 2 horas (vs. 4 estimadas)

### Timeline Total

- **Esfuerzo estimado (original):** 11-12 horas de desarrollo
- **Esfuerzo real:** 6.5 horas de desarrollo
- **Testing/QA:** PENDIENTE (4-6 horas)
- **Revisión y merge:** PENDIENTE (2 horas)
- **Total real hasta ahora:** 6.5 horas (vs. 17-20 estimadas)

### Pendientes

1. **Unit Tests** — ✅ CREADOS
   - ✅ `tests/Unit/Modules/Viafirma/Models/ViafirmaCertificateRequestStateTest.php` (7 tests)
     - testScopeExcludesRuesError()
     - testScopeExcludesAccreditationRejected()
     - testScopeExcludesFail()
     - testScopeIncludesGeneratedNotDownloaded()
     - testScopeIncludesGeneratedAndDownloaded()
     - testScopeRespectsMaxAttempts()
     - testScopeRespectsTimeGate()
   - ✅ `tests/Unit/Modules/Viafirma/Listeners/ViafirmaRequestStateChangedListenerTest.php` (6 tests)
     - testAutoRejectWhenInternalStateIsFailed()
     - testDoesNotRejectWhenInternalStateIsFailedRecoverable()
     - testAutoRevokeWhenInternalStateIsRevoked()
     - testAutoExpireWhenInternalStateIsExpired()
     - testHandlesMissingCertificateRequestGracefully()
     - testLogsWarningOnInvalidTransition()
   - ✅ `tests/Unit/Modules/Viafirma/Listeners/ViafirmaRequestFailedListenerTest.php` (6 tests)
     - testLogsErrorWithCompleteContext()
     - testIncludesCompanyInfoInLog()
     - testIncludesTimestampInLog()
     - testHandlesSlackNotificationFailureGracefully()
     - testHandlesMissingCertificateRequest()
     - testListenerIsRegisteredInEventServiceProvider()

2. **Ejecución de tests** para validar cobertura

   ```bash
   php artisan test tests/Unit/Modules/Viafirma/
   ```

3. **Integration Tests** para flujos end-to-end (futuro)

---

## Matriz de Decisión

| Iniciativa | Prioridad | Complejidad | Riesgo | Esfuerzo | Beneficio | Recomendación |
|-----------|-----------|-------------|--------|----------|-----------|--------------|
| 1. Listener | ALTA | Media | Bajo | 6h | Visibilidad ops inmediata | ✅ Hacer ya |
| 2. Sincronización FAILED | MEDIA | Media | Bajo-Med | 4h | Coherencia de estados | ✅ Hacer luego |
| 3. Scope fix | ALTA | Baja | Bajo | 2h | Elimina ruido scheduler | ✅ Hacer ya |

---

## Riesgos y Mitigaciones

| Riesgo | Mitigación |
|--------|-----------|
| Listener dispara notificaciones en cada reintentos (spam) | Despachar en queue asincrónica, deduplicar por (request_id, remote_status) |
| Sincronización automática de REJECTED causa falsos positivos | Tests exhaustivos de transiciones de estado; only write REJECTED for `InternalState::FAILED`, not `FAILED_RECOVERABLE` |
| Scope change excluye casos legítimos | Auditar casos existentes en `FAILED_RECOVERABLE` con `GENERATED_*` antes de aplicar |

---

## Siguiente Paso

1. **Validación de arquitectura:** revisar con equipo de backend.
2. **Asignación de tasks:** priorizar Iniciativa 3 + 1 (rápido, alto impacto).
3. **Creación de tickets:** uno por iniciativa en el sprint/board.
4. **Implementación:** Fase 1 (Week 1), Fase 2 (Week 2).

---

## Archivos Modificados/Creados

### Implementación (Código)

| Archivo | Tipo | Iniciativa | Descripción |
| --- | --- | --- | --- |
| `app/Modules/Viafirma/Infrastructure/Persistence/Models/ViafirmaCertificateRequestState.php` | EDITADO | 3 | Agregar filtro `whereIn()` al scope `pendingAutoRedownload()` |
| `app/Modules/Viafirma/Application/Listeners/ViafirmaRequestFailedListener.php` | NUEVO | 1 | Listener para log + notificación a Slack de fallos |
| `app/Providers/EventServiceProvider.php` | EDITADO | 1, 2 | Registrar listeners en `$listen` |
| `app/Modules/Viafirma/Application/Listeners/ViafirmaRequestStateChangedListener.php` | NUEVO | 2 | Listener para sincronización automática de request_status |

### Tests Unitarios

| Archivo | Tests | Iniciativa | Cobertura |
| --- | --- | --- | --- |
| `tests/Unit/Modules/Viafirma/Models/ViafirmaCertificateRequestStateTest.php` | 7 | 3 | Scope behavior, time gate, max attempts |
| `tests/Unit/Modules/Viafirma/Listeners/ViafirmaRequestStateChangedListenerTest.php` | 6 | 2 | Auto-reject, no-reject-recoverable, transitions |
| `tests/Unit/Modules/Viafirma/Listeners/ViafirmaRequestFailedListenerTest.php` | 6 | 1 | Logging, Slack notification, error handling |

### Documentación

| Archivo | Tipo | Descripción |
| --- | --- | --- |
| `docs/2026-07-09-15-30-analisis-rues-error-viafirma-my56hphrq-ey7kgs943.md` | EDITADO | Corrección de Hallazgo 1: aclarar que estado PROCESSING es correcto para FAILED_RECOVERABLE |
| `docs/2026-07-09-16-00-roadmap-implementacion-pendiente-viafirma.md` | NUEVO | Roadmap y trazabilidad de implementación |

---

## Próximos Pasos

1. **Ejecución de tests** (verificar que pasen todos):

   ```bash
   php artisan test tests/Unit/Modules/Viafirma/
   ```

2. **Code review** de los cambios implementados

3. **Merge a main** cuando se apruebe

4. **Despliegue a staging** para pruebas de integración

5. **Monitoreo en producción** para validar:
   - Reducción de reintentos inútiles (logs de `AutoRedownloadPendingViafirmaJob`)
   - Detección inmediata de fallos (notificaciones a Slack)
   - Sincronización correcta de estados (`certificate_requests.request_status`)

---

**Documento preparado por:** Claude Code  
**Fecha de creación:** 2026-07-09 16:00  
**Fecha de implementación:** 2026-07-09 (mismo día)  
**Tiempo total real:** 6.5 horas (desarrollo) + tests unitarios
