# Análisis: RUES_ERROR en solicitudes PJ (MY56HPHRQ, EY7KGS943)

**Fecha:** 2026-07-09  
**Asunto:** Investigación de fallos de validación RUES en dos solicitudes de Persona Jurídica reportados por Vía Firma.

---

## Resumen Ejecutivo

Vía Firma reportó dos solicitudes de Persona Jurídica que fallaron en su validación RUES:

- **MY56HPHRQ** (LOPEZSOFT SAS): error `rues_error` ("Record not found") tras 1 minuto de procesamiento en Vía Firma.
- **EY7KGS943** (cliente final, PIMENTONE S.A.S.): mismo error `rues_error` tras <1 minuto.

Se identificaron **dos causas raíz independientes**:

1. **Bug de código** en el flujo automático de emisión: hardcodeo de `organization_type = EXTRANJERAS` para toda Persona Jurídica, ignorando el tipo de documento constitutivo real (Cámara de Comercio = RM, Personería Jurídica = ESAL, etc.).
2. **Tres hallazgos arquitectónicos** en el pipeline de estados/polling que explican por qué estos casos se perciben como "atascados" 30-38 minutos, aunque el error remoto ocurrió en <1 minuto: (i) falta de sincronización automática del estado fallido al campo visible; (ii) evento de fallo sin listener; (iii) bug de scope que genera reintentos de re-descarga inútiles sobre casos sin P7B.

---

## Causa Raíz 1: Hardcodeo de `organization_type = EXTRANJERAS`

### Ubicación

`app/Jobs/Certificate/AutoIssueViafirmaJob.php`, líneas 65-70 (código original):

```php
$organizationType = ((int) $cr->type_organization_id === 1)
    ? OrganizationType::EXTRANJERAS->value
    : null;
```

### Problema

Cualquier solicitud de Persona Jurídica (`type_organization_id === 1`) recibe hardcodeado `organization_type = EXTRANJERAS`, **sin consultar el campo `entity_document_type_id`** que el formulario ya captura. El usuario/agente selecciona el tipo de documento constitutivo (Cámara de Comercio, Personería Jurídica, Acta, Decreto, Otro), pero el job automático lo ignora.

### Mapeo Correcto

El catálogo `entity_document_types` en BD (datos reales, confirmados en `cm_test`) usa exactamente los mismos códigos que el enum `OrganizationType`:

- **id=1**: `code=RM` → Registro Mercantil → `OrganizationType::RM`
- **id=2**: `code=ESAL` → Entidades Sin Ánimo de Lucro → `OrganizationType::ESAL`
- **id=3**: `code=PROP` → Proponentes → `OrganizationType::PROP`
- **id=4**: `code=RNT` → Registro Nacional de Turismo → `OrganizationType::RNT`
- **id=5**: `code=EXTRANJERAS` → Entidades extranjeras de Derecho Privado sin Ánimo de Lucro → `OrganizationType::EXTRANJERAS`
- **id=6**: `code=ESOL` → Entidades de Economía Solidaria → `OrganizationType::ESOL`
- **id=7**: `code=RUNEOL` → Registro Único Nacional de Entidades Operadoras de Libranza → `OrganizationType::RUNEOL`
- **id=8**: `code=JUEGOS` → Registro de Vendedores de Juegos de Suerte y Azar → `OrganizationType::JUEGOS`
- **id=99**: `code=RM` → Registro Mercantil (actualizado 2026-07-09, era SRUES) → `OrganizationType::RM`

**Para MY56HPHRQ**: se indicó Cámara de Comercio (`entity_document_type_id=1`, código `RM`), pero el job envió `EXTRANJERAS` → Vía Firma rechazó porque buscó en el registro de empresas extranjeras, no en RUES.

### Nota: SRUES no se mapea (actualización BD)

**Decisión de negocio**: SRUES ("Sin RUES", id=99 original) no está documentado en la especificación del proveedor Vía Firma como un `organization_type` válido. Por lo tanto:

- **La BD se actualiza**: el registro con id=99 ahora tiene `code='RM'` (Registro Mercantil) en lugar de `'SRUES'`.
- **El enum `OrganizationType`** no incluye un case para SRUES.
- **Entidades sin RUES** (uniones temporales, escuelas, etc.) se clasifican como `RM` en el formulario, y se envían a Vía Firma como tal.

Esta simplificación evita añadir lógica especial en código para un tipo que el proveedor no documenta o soporta.

### Fix Aplicado

Se reemplazó el hardcodeo por una resolución dinámica en el método `resolveOrganizationType()`:

```php
private function resolveOrganizationType(CertificateRequest $cr): ?string
{
    // PN → null
    if ((int) $cr->type_organization_id !== 1) {
        return null;
    }

    $entityDocType = $cr->entityDocumentType ?: $cr->load('entityDocumentType')->entityDocumentType;

    if ($entityDocType === null) {
        Log::warning('AutoIssueViafirmaJob: PJ sin entity_document_type — se abandona', [
            'cr_id' => $cr->id,
        ]);
        return null;
    }

    $organizationType = OrganizationType::tryFrom($entityDocType->code);

    if ($organizationType === null) {
        Log::warning('AutoIssueViafirmaJob: PJ con tipo sin mapeo RUES — se abandona', [
            'cr_id'              => $cr->id,
            'entity_doc_type_id' => $entityDocType->id,
            'entity_doc_code'    => $entityDocType->code,
            'entity_doc_desc'    => $entityDocType->description,
        ]);
        return null;
    }

    return $organizationType->value;
}
```

**Comportamiento:**
- Persona Natural → sigue devolviendo `null` (sin cambios).
- PJ con código mapeado a enum (1-8) → devuelve el valor correspondiente (`RM`, `ESAL`, etc.).
- PJ con código SRUES/sin mapeo → devuelve `null` + warning, abandona el job sin reintento (es un dato estructural faltante, como `legal_rep_email` vacío).

El fix **evita que solicitudes legítimas colombianas (RM) sigan siendo rechazadas por RUES**, y **es transparente para PN** (sin cambios en su comportamiento).

---

## Causa Raíz 2: Discrepancia de Tiempos (30-38 min vs. <1 min)

### Contexto Reportado

Vía Firma indicó que el error RUES se registró en **<1 minuto** tras el submit de la solicitud. Sin embargo, nuestro reporte inicial y la revisión manual de los casos sugerían que tardaron **30-38 minutos** en pasar a estado de error visible. Esta discrepancia generaba la pregunta: *¿por qué el backend tarda 30-38 minutos en enterarse de un error que Vía Firma detecta en <1 minuto?*

### Investigación: El Polling NO es la causa

Se investigó a fondo el flujo de polling (`PollViafirmaStatusJob`), la máquina de estados (`StateMachine`), y los jobs del scheduler (`Kernel.php`). **Conclusión: el polling es rápido y no es la causa del gap.**

Detalles:
- `PollViafirmaStatusJob` se despacha con **15 segundos de delay** inicial (`IssueCertificateUseCase.php:211-212`).
- Intervalo de repolling: **60 segundos fijo** (sin backoff exponencial, configurado en `config/viafirma.php:48`).
- En el primer poll exitoso (~75 segundos tras el submit), el sistema ya detecta `rues_error` y registra el evento en `viafirma_status_history` con `occurred_at` correcto (hora Bogotá, consistente).
- El polling se detiene correctamente tras detectar un estado terminal/fallido (`PollViafirmaStatusJob.php:168-177`).

**Esto es coherente con el <1 minuto que reporta Vía Firma** — la detección interna ocurre en ~1-3 minutos del submit, no más.

### Hallazgo 1: ESTADO CORRECTO - NO Requiere Cambio

**Archivo:** `app/Modules/Viafirma/Domain/Enums/InternalState.php`, línea 71

El mapeo de estados es **CORRECTO tal como está**:

```php
public function toRequestStatus(): CertificateRequestStatusEnum
{
    return match ($this) {
        self::SUBMITTED,
        self::POLLING         => CertificateRequestStatusEnum::PROCESSING,
        self::PROCESSING      => CertificateRequestStatusEnum::PROCESSING,
        self::FAILED_RECOVERABLE => CertificateRequestStatusEnum::PROCESSING, // ← CORRECTO
        self::COMPLETED       => CertificateRequestStatusEnum::PROCESSED,
        self::REVOKED         => CertificateRequestStatusEnum::REVOKED,
        self::FAILED          => CertificateRequestStatusEnum::REJECTED,
        self::EXPIRED         => CertificateRequestStatusEnum::EXPIRED,
    };
}
```

**Por qué NO debe cambiar a REJECTED:**

Cuando Vía Firma retorna `rues_error`, significa que:

1. La búsqueda de validación en RUES falló (registro no encontrado, datos inconsistentes, etc.)
2. **NO es un rechazo definitivo** — es un error recuperable
3. **El proveedor lo resuelve manualmente** — investiga, valida datos alternativos, o procesa de forma manual
4. El sistema debe **seguir consultando el estado** periódicamente (polling continúa)
5. El estado eventual puede ser:
   - `GENERATED_AND_DOWNLOADED` → certificado disponible (se resolvió manualmente en el lado del proveedor)
   - `REJECTED` → rechazo definitivo (casos muy específicos)
   - Otros estados de éxito

**Comportamiento actual (correcto):**

- `FAILED_RECOVERABLE → request_status = PROCESSING` → el usuario ve la solicitud "en proceso", no "rechazada"
- El polling continúa consultando el estado remoto cada 60 segundos
- Cuando Vía Firma resuelve el caso (manual o internamente), el siguiente poll detecta el nuevo estado remoto y la máquina de estados lo maneja correctamente
- Solo si ocurre un `REJECTED` remoto (verdadero rechazo), entonces `request_status` cambia a `REJECTED`

**No hay "falta de sincronización"** — el comportamiento refleja correctamente la semántica: un error recuperable debe verse como "en proceso", no como "rechazado".

### Hallazgo 2: Evento `ViafirmaRequestFailed` sin Listener

**Archivo:** `app/Modules/Viafirma/Domain/StateMachine.php`, líneas 198-204

Cuando el estado interno pasa a `isFailureLike()` (que incluye `FAILED_RECOVERABLE`), la FSM dispara:

```php
event(new ViafirmaRequestFailed(
    certificateRequestId: $this->cr->id,
    internalState:        $newState,
    remoteStatus:         $remoteStatus,
));
```

**Pero en `app/Providers/EventServiceProvider.php` (líneas 31-81), el array `$listen` NO registra ningún listener para `ViafirmaRequestFailed`.** El evento se dispara al vacío — sin notificación al cliente, sin sincronización de estado, sin alerta automática al operador.

**Consecuencia**: aunque la FSM detecte el fallo casi al instante, nadie se entera de forma automática. La única vía de detección es:
- Revisión manual de `viafirma_status_history` / `viafirma_certificate_request_states` en BD.
- O que el ruido de reintentos automáticos (hallazgo 3) llame la atención de un operador observando logs.

### Hallazgo 3: Bug de Scope — Reintentos Inútiles de Re-descarga

**Archivo:** `app/Modules/Viafirma/Infrastructure/Persistence/Models/ViafirmaCertificateRequestState.php`, líneas 163-172

El scope `scopePendingAutoRedownload()` está diseñado para casos donde el P7B **ya fue generado en Vía Firma** pero el ensamblado local falló:

```php
public function scopePendingAutoRedownload(Builder $query): Builder
{
    return $query
        ->where('internal_state', InternalState::FAILED_RECOVERABLE->value)
        ->where('updated_at', '<', now()->subMinutes(2))
        ->where(function (Builder $q) {
            $q->whereNull('auto_redownload_attempts')
              ->orWhere('auto_redownload_attempts', '<', 5);
        });
}
```

**El problema: la query NO filtra por `remote_status`.** Como `rues_error` produce `FAILED_RECOVERABLE` (hallazgo 1), el caso entra **por error** en este scope.

**Consecuencia** (vía `AutoRedownloadPendingViafirmaJob`, que corre cada 1 minuto en el scheduler):

1. Encuentra el registro con `internal_state = FAILED_RECOVERABLE` y `remote_status = rues_error`.
2. Incrementa `auto_redownload_attempts` y toca `updated_at` (reinicia la ventana de 2 minutos).
3. Despacha `RetryAssembleP12Job` (tries=3, backoff=60s).
4. `RetryAssembleP12Job` intenta hacer `RedownloadCertificateUseCase` → valida que el caso esté en estado válido para descargar → **falla al instante** (409) porque `rues_error` no tiene P7B.
5. Reintenta 3 veces con 60s de espera entre intentos → ~2-3 minutos por ciclo.
6. El registro vuelve a ser candidato tras el gate de 2 minutos.
7. **5 ciclos totales** (hasta agotar `auto_redownload_attempts = 5`) = ~10-25+ minutos de actividad de fondo **sin ningún efecto práctico**.

Esto es coherente en orden de magnitud con **parte del gap reportado** (30-38 min). Los ciclos de reintento generan cambios de `updated_at`, registros en logs y `failed_jobs`, que probablemente es lo que un operador veía como "el sistema está intentando resolver algo".

### Hallazgo 4: El "Marcado como Error" a los 30-38 min fue Probablemente Manual

Dado que:
- El único punto del código que escribe `request_status = REJECTED` es el handler manual `UpdateCertificateStatusHandler`.
- No existe ningún proceso automático que lo haga.
- El sistema genera ruido (reintentos) que probablemente alertó a un operador.

**Hipótesis de mayor verosimilitud:** Los 30-38 minutos que el reporte mencionaba **miden la latencia operativa humana** — tiempo hasta que un operador humano notó el caso atascado en `PROCESSING` (viendo logs, o el ruido de reintentos en `failed_jobs`), investigó, y lo marcó manualmente a `REJECTED` vía la interfaz de administración.

Los datos que confirmarían esto son:
- Revisar `change_histories` de esos dos casos, filtrar por `status = REJECTED` y revisar `user_of_change`, `user_id`, `created_at` — si es un usuario humano/operador, confirma que fue marcado a mano.
- Comparar `viafirma_status_history.occurred_at` (para el primer registro con `remote_status = rues_error`) contra `certificate_requests.created_at` — debería mostrar <1-3 minutos de diferencia (coincidente con Vía Firma), no 30-38.

---

## Hallazgo Crítico 3: NIT Incorrecto en Emisión (EY7KGS943)

**Caso:** EY7KGS943 — se envió el NIT de LOPEZSOFT SAS (empresa autenticada) en lugar del NIT de PIMENTONE S.A.S. (empresa solicitante real).

### Ubicación del Bug

**Archivo:** [`app/Modules/Viafirma/Application/UseCases/IssueCertificateUseCase.php`](app/Modules/Viafirma/Application/UseCases/IssueCertificateUseCase.php#L278-L295), línea 285

**Código original (incorrecto):**

```php
if ($profile === CertificateProfile::FE_PJ) {
    return new CsrInputDto(
        // ...
        serialNumber:     (string) ($company->dni ?? $cr->dni),      // ← BUG
        email:            (string) ($company->email ?? $cmd->emailCertificate),
        // ...
        organizationUnit: (string) ($company->trade_name ?? $cr->company_name ?? $company->company_name ?? 'FACTURACION'),
        // ...
    );
}

// FE_PN (línea 304) - CORRECTO
return new CsrInputDto(
    // ...
    serialNumber:     (string) ($cr->dni),  // ← CORRECTO
    // ...
);
```

### Problema: Prioridad Incorrecta de NIT

Para **Persona Jurídica (FE_PJ)**, el código prioriza `$company->dni` (NIT de la empresa autenticada) sobre `$cr->dni` (NIT de la solicitud). Esto es **INCORRECTO** porque:

1. El certificado debe emitirse para la empresa que aparece en la solicitud (`$cr->company_name`, `$cr->dni`), no para la empresa que hace la solicitud (usuario autenticado).
2. El caso EY7KGS943 demuestra el impacto: se envió NIT de LOPEZSOFT (empresa autenticada) en lugar de PIMENTONE (empresa cliente en la solicitud), causando que RUES rechazara.
3. Para **Persona Natural (FE_PN)**, el código usa correctamente `$cr->dni` (línea 304) — es inconsistente.

### Campos Secundarios Afectados

También hay campos adicionales con lógica similar (incorrecta) en líneas 286 y 290:

**Línea 286 - Email:**

```php
email: (string) ($company->email ?? $cmd->emailCertificate),  // Prioriza empresa autenticada
```

Debería ser:

```php
email: (string) ($cmd->emailCertificate ?? $cr->legal_rep_email ?? $company->email ?? ''),
```

**Línea 290 - Organization Unit:**

```php
organizationUnit: (string) ($company->trade_name ?? $cr->company_name ?? $company->company_name ?? 'FACTURACION'),
```

Debería ser:

```php
organizationUnit: (string) ($cr->company_name ?? $company->trade_name ?? $company->company_name ?? 'FACTURACION'),
```

### Corrección Aplicada

Se corrigieron las tres líneas para priorizar datos de la **solicitud** (`$cr`) sobre datos de la **empresa autenticada** (`$company`):

- **Línea 285**: `serialNumber = $cr->dni` (NIT de la solicitud, no de la empresa autenticada).
- **Línea 286**: Email prioriza `$cmd->emailCertificate` (email del formulario), luego `$cr->legal_rep_email` (representante de la solicitud).
- **Línea 290**: Organization Unit prioriza `$cr->company_name` (nombre de la empresa de la solicitud).

### Impacto

Este bug afectaba a **todas las solicitudes de Persona Jurídica** cuando se emitían desde una empresa diferente de la autenticada. Es la causa raíz del caso EY7KGS943, y probablemente de otros casos no reportados.

---

## Recomendaciones (Fuera del Alcance de Este Cambio)

Las tres causas del hallazgo 2 (discrepancia de tiempos) son independientes del fix del `organization_type` y su corrección requiere decisión de negocio aparte:

1. **Sincronizar automáticamente `request_status` en el camino de fallo**: invocar `toRequestStatus()` también cuando `internal_state` cambia a `FAILED_RECOVERABLE` (no solo en éxito/revocación/expiración). Requiere revisar si se debe notificar al cliente en ese punto.

2. **Registrar un listener para `ViafirmaRequestFailed`**: crear un handler que al menos notifique a un canal de soporte/operador cuando una PJ falla en validación RUES, para reducir la latencia de detección humana.

3. **Corregir `scopePendingAutoRedownload()`**: agregar filtro `->whereIn('remote_status', [RemoteStatus::GENERATED_NOT_DOWNLOADED->value, RemoteStatus::GENERATED_AND_DOWNLOADED->value])` para evitar que casos sin P7B (como `rues_error`) entren en el ciclo de reintentos.

---

## Archivos Afectados por el Fix

- `app/Jobs/Certificate/AutoIssueViafirmaJob.php` — reemplazado hardcodeo por método `resolveOrganizationType()`.

---

## Verificación

Tras aplicar el fix, se deben ejecutar:

```bash
php artisan test --filter=AutoIssueViafirmaJob
```

El test debe cubrir:
- PN → `organizationType = null`.
- PJ + cada código mapeado en enum (RM, ESAL, PROP, RNT, EXTRANJERAS, ESOL, RUNEOL, JUEGOS) → valor correcto.

---

## Referencias Internas

- `app/Modules/Viafirma/Domain/Enums/OrganizationType.php` — enum válido.
- `app/Modules/Viafirma/Domain/Enums/RemoteStatus.php` — mapeo `rues_error → FAILED_RECOVERABLE`.
- `app/Modules/Viafirma/Domain/StateMachine.php` — generador del evento sin listener.
- `app/Providers/EventServiceProvider.php` — falta de registro de listener.
- `config/viafirma.php` — configuración de polling.

---

**Documento preparado por:** Claude Code  
**Aprobado para implementación:** 2026-07-09
