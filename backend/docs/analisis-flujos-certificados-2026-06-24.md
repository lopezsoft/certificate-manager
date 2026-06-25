# Análisis de Flujos de Emisión de Certificados
**Documento generado:** 2026-06-24  
**Proyecto:** Certificate Manager — Backend Laravel  
**Versión API Viafirma referenciada:** v3.4.58 (Sandbox)

---

## 1. Arquitectura General

El sistema soporta **dos proveedores de emisión** de certificados digitales, resueltos por cascada:

```
payload override (admin)
    ↓
companies.issuance_provider  ← campo por empresa
    ↓
config CERTIFICATE_ISSUANCE_PROVIDER  ← variable de entorno
    ↓
fallback: 'mail'
```

La fábrica que resuelve esto vive en:  
`app/Services/Certificate/CertificateIssuanceProviderFactory.php`

---

## 2. Flujo EMAIL (Proveedor Legado)

### 2.1 Cómo funciona

El flujo `email` es el flujo **legacy y manual**. El proceso completo es:

1. El cliente llena el formulario de solicitud de certificado en el frontend.
2. El administrador/gestor revisa los documentos adjuntos y presiona **"Enviar"**.
3. El sistema (`CertificateRequestMailService::sendMail()`) cambia el estado a `PROCESSING` y envía un correo a la CA (Autoridad Certificadora) definida en `config('certificate.mail.receipt_email')`.
4. La CA procesa el certificado **manualmente** y lo envía de vuelta por correo.
5. El administrador sube el certificado recibido al sistema.
6. El gestor cambia manualmente el estado a `PROCESSED`.

### 2.2 Estado en `certificate_requests`

| Momento | `request_status` |
|---|---|
| Creación | `DRAFT` |
| Al enviar el correo | `PROCESSING` |
| Al subir el certificado (manual) | `PROCESSED` |

### 2.3 Registros de historial

- **`change_histories`**: Se registra un cambio con `status = PROCESSING`, `user_of_change = 'MANAGER'` al enviar el correo.
- **`viafirma_status_history`**: **NO se registra nada.** Esta tabla es exclusiva del proveedor Viafirma.

### 2.4 ¿Qué sucede con `/renew` para el flujo EMAIL?

El `RenewCertificateUseCase` **NO diferencia por proveedor**. Al pagar la renovación, `PaymentOrchestrator::processCertificateRenewal()` hace lo siguiente para **ambos proveedores**:

```php
$cr->update([
    'life'            => 2,
    'expiration_date' => Carbon::parse($cr->issued_at)->addYears(2),
]);
ChangeHistory::create([...  'user_of_change' => 'SYSTEM (Renovación Pagada)' ...]);
```

**Consecuencias para proveedor EMAIL:**

| | Estado actual | Comportamiento correcto |
|---|---|---|
| BD | `life=2` y `expiration_date` actualizados ✅ | OK |
| Notificación | ❌ No se notifica a nadie | Enviar correo al administrador para que genere y envíe un nuevo certificado físico |
| Archivo físico | ❌ El certificado original fue emitido por 1 año | El administrador debe emitir manualmente un nuevo certificado con vigencia extendida |

> **Nota:** Para el flujo email, el certificado físico sí fue emitido por 1 año (la CA lo generó así), por lo que la renovación comercial implica gestión manual. Ver §5.2.

---

## 3. Flujo VIAFIRMA (Proveedor Moderno)

### 3.1 Diagrama de estados completo

```
DRAFT
  │
  ▼ IssueCertificateUseCase
CSR_GENERATED  ──── (genera par RSA-2048 + CSR)
  │
  ▼
SUBMITTED ──── POST /request/fromCSR ──────► cod_request + publicId
  │
  ▼ (polling cada 60s)
POLLING ◄──────────────────────────────────────────────────────────────────┐
  │                                                                          │
  │   GET /request/{codRequest}/status                                       │
  │                                                                          │
  ├── rues_check, inProcess, accreditation*, All_Ok, etc. → sigue POLLING ──┘
  │
  ├── [accreditation] → se puede pedir el KYC link
  │       GET /services/accreditation/{codRequest}
  │       → link disponible solo mientras dure este estado
  │
  ├── rues_error, accreditation_rejected → FAILED_RECOVERABLE
  │       (operador RA debe intervenir)
  │
  ├── fail → FAILED (terminal irrecuperable)
  │
  └── Generated_Not_Downloaded → READY_TO_DOWNLOAD
          │
          │   ← AQUÍ se llama a revocationCode (API v3.4.58)
          │   GET /request/{codRequest}/revocationCode
          │   → guardado en viafirma_certificate_request_states.revocation_request_code
          │
          ▼ DownloadP7bJob
      DOWNLOADED ──── GET /downloadCertificateServlet?req={publicId}
          │           → P7B binario guardado en storage
          ▼ AssembleP12Job
      ASSEMBLED ──── combina P7B + llave privada del KeyVault → P12
          │          → certificate_requests.request_status = PROCESSED
          ▼
      COMPLETED (terminal exitoso)
          │
          ▼ (si se paga renovación o revocación voluntaria)
      REVOKED (terminal)
```

### 3.2 Tabla de Jobs y frecuencias

| Job | Tipo | Frecuencia | Responsabilidad |
|---|---|---|---|
| `PollViafirmaStatusJob` | Cola auto-reprogramable | ~cada 60s por trámite | Llama `GET /request/{codRequest}/status` |
| `ReviveStalledViafirmaPollsJob` | Cron Kernel | Cada 5 minutos | Resucita polls huérfanos (sin `next_poll_at`) |
| `RetryStalledIssuancesJob` | Cron Kernel | Cada 1 minuto | Reintenta emisiones `PROCESSING` bloqueadas >3min |
| `DownloadP7bJob` | Cola (cascade) | Al detectar `READY_TO_DOWNLOAD` | Llama `GET /downloadCertificateServlet?req={publicId}` |
| `AssembleP12Job` | Cola (cascade) | 5s tras descarga P7B | Ensambla P12 con llave privada del KeyVault |
| `AutoRedownloadPendingViafirmaJob` | Cron Kernel | Cada 5 minutos | Reintenta `FAILED_RECOVERABLE` (máx 5 intentos) |
| `PurgeExpiredKeysJob` | Cron Kernel | Diario 02:00 COT | Destruye llaves privadas de solicitudes terminales (>72h) |
| `AutoRevokeUnpaidCertificatesJob` | Cron Kernel | Diario 03:00 COT | Revoca certificados con deuda usando `revocation_request_code` |
| `MarkExpiredCertificatesJob` | Cron Kernel | Diario 04:00 COT | Marca expiración técnica/comercial |

### 3.3 Qué registra en historial cada Job

#### `PollViafirmaStatusJob` (vía `StateMachine`)
- **`viafirma_status_history`**: Registro en **cada poll**, incluso si el estado no cambia. Campos: `previous_state`, `new_state`, `remote_status`, `raw_response`, `attempt_number`.
- **`change_histories`**: NO escribe directamente.

#### `DownloadP7bJob`
- **`viafirma_status_history`**: No escribe (la FSM registró la transición en el poll).
- **`change_histories`**: `status = PROCESSING`, `"Certificado recibido del proveedor — preparando archivo final."`, `user_of_change = 'SYSTEM'`.
- **`file_managers`**: Registra el P7B descargado con `document_type = 'P7B_CERTIFICATE'`.

#### `AssembleP12Job`
- **`viafirma_status_history`**: Dos entradas: `DOWNLOADED → ASSEMBLED` y `ASSEMBLED → COMPLETED`.
- **`change_histories`**: `status = PROCESSED`, `"Certificado digital generado exitosamente y listo para descarga."`.
- **`file_managers`**: Registra el ZIP (`CERTIFICATE`) y la referencia de llave privada (`PRIVATE_KEY`).
- **`certificate_requests`**: Actualiza `request_status = PROCESSED`, `issued_at`, `cert_valid_to`, `expiration_date`.

---

## 4. Mapa de Endpoints Viafirma

| Endpoint Viafirma | Cuándo se llama | Job/UseCase | Dónde se persiste |
|---|---|---|---|
| `POST /request/fromCSR` | Al emitir | `IssueCertificateUseCase` | `viafirma_certificate_requests`: `cod_request`, `public_id` |
| `GET /request/{cod}/status` | Cada 60s | `PollViafirmaStatusJob` | `viafirma_certificate_request_states.remote_status`, `viafirma_status_history` |
| `GET /services/accreditation/{cod}` | Bajo demanda frontend | `GetKycLinkUseCase` | ❌ **No persistido** (mejora propuesta §6.1) |
| `GET /request/{cod}/revocationCode` | Al detectar `Generated_Not_Downloaded` | `DownloadP7bJob` | `viafirma_certificate_request_states.revocation_request_code` |
| `GET /downloadCertificateServlet?req={publicId}` | Tras `READY_TO_DOWNLOAD` | `DownloadP7bJob` | `viafirma_certificate_request_states.p7b_storage_path`, `file_managers` |
| `POST /request/revoke/code/{revokingCode}` | Al revocar | `RevokeCertificateUseCase` | `viafirma_certificate_request_states.revoked_at` |

---

## 5. `/renew` — Análisis correcto por proveedor

### 5.1 Modelo de negocio: cómo Viafirma genera los certificados

**Viafirma siempre emite el certificado con 2 años de validez criptográfica** (es el mínimo que acepta la CA). Sin embargo, el sistema de Certificate Manager permite que el cliente elija **1 año o 2 años** comercialmente al momento de solicitar:

| Elección del cliente | `life` en BD | `expiration_date` en BD | P12 físico (X.509) |
|---|---|---|---|
| 1 año | `1` | `issued_at + 1 año` | Válido por **2 años** criptográficamente |
| 2 años | `2` | `issued_at + 2 años` | Válido por **2 años** criptográficamente |

Cuando el cliente elige 1 año y luego paga la **renovación**, el sistema simplemente **desbloquea el segundo año comercial** que ya estaba grabado en el P12. No se re-emite nada.

### 5.2 Implementación actual para VIAFIRMA — ✅ Correcta

El `PaymentOrchestrator::processCertificateRenewal()` actualiza únicamente la tabla `certificate_requests`:

```php
$cr->update([
    'life'            => 2,
    'expiration_date' => Carbon::parse($cr->issued_at)->addYears(2),
]);
```

Esto es **correcto para Viafirma** porque:
- El P12 ya tiene 2 años de validez criptográfica desde el momento de la emisión.
- Solo se extiende la fecha comercial en nuestra BD.
- **No se necesita re-emitir ni modificar el P12.**

### 5.3 Implementación actual para EMAIL — ❌ Incompleta

Para el flujo `email`, el certificado físico **sí fue emitido por la CA por 1 año** (no existe el concepto de 2 años implícitos como en Viafirma). Por lo tanto, al pagarse la renovación:

- ✅ La BD se actualiza correctamente.
- ❌ **Nadie es notificado** — ni el administrador ni el cliente.
- ❌ El administrador debe solicitar manualmente a la CA un nuevo certificado con vigencia extendida y enviárselo al cliente.

**Gap real:** El `RenewCertificateUseCase` no diferencia por proveedor. Al detectar `issuance_provider = 'mail'`, debería disparar un correo al administrador con el aviso de renovación pendiente.

### 5.4 Implementación propuesta para EMAIL

**Archivos a modificar:**
- `app/Services/PaymentOrchestrator.php` — método `processCertificateRenewal()`
- `app/Models/Company.php` — agregar `issuance_provider` a `$fillable`

**Lógica en `PaymentOrchestrator::processCertificateRenewal()`:**

```php
private function processCertificateRenewal(CertificateOrder $order): void
{
    $cr = $order->certificateRequest;
    if (! $cr) {
        Log::error('[RENEWAL] Orden de renovación sin certificado asociado.', ['order_id' => $order->id]);
        return;
    }

    // Extender vida y expiración comercial (válido para ambos proveedores)
    $newExpiration = $cr->issued_at
        ? \Carbon\Carbon::parse($cr->issued_at)->addYears(2)
        : \Carbon\Carbon::now()->addYears(2);

    $cr->update([
        'life'            => 2,
        'expiration_date' => $newExpiration,
    ]);

    ChangeHistory::create([
        'certificate_request_id' => $cr->id,
        'user_id'                => null,
        'user_of_change'         => 'SYSTEM (Renovación Pagada)',
        'status'                 => $cr->request_status,
        'comments'               => "Certificado renovado exitosamente vía orden {$order->uuid}.",
    ]);

    // ── Notificación al administrador solo para proveedor email ─────────────
    $provider = $cr->company?->issuance_provider ?? 'mail';
    if ($provider === 'mail') {
        $receiptEmail = config('certificate.mail.receipt_email');
        \Illuminate\Support\Facades\Mail::to($receiptEmail)->queue(
            new \App\Mail\CertificateRenewalAdminNotification($cr, $order)
        );
        Log::info('[RENEWAL] Notificación enviada al administrador (proveedor email).', [
            'certificate_request_id' => $cr->id,
            'order_uuid'             => $order->uuid,
            'to'                     => $receiptEmail,
        ]);
    }

    Log::info('[RENEWAL] Renovación procesada exitosamente.', [
        'order_id'               => $order->id,
        'certificate_request_id' => $cr->id,
        'provider'               => $provider,
    ]);
}
```

**Mailable requerido — `app/Mail/CertificateRenewalAdminNotification.php`:**

```php
// Datos mínimos que debe exponer al template:
// - $certificateRequest->company->company_name
// - $certificateRequest->dni / $certificateRequest->dv
// - $certificateRequest->id
// - $order->uuid / $order->total_amount
// - $newExpiration (issued_at + 2 años)
// Asunto sugerido:
// "Renovación pagada — Certificado #{$cr->id} empresa {$company_name} requiere nuevo certificado"
```

**Template blade sugerido:** `resources/views/emails/certificate_renewal_admin.blade.php`

```
Asunto: Renovación pagada — Certificado #{{ $cr->id }} requiere emisión manual

La empresa {{ $company_name }} (NIT: {{ $dni }}-{{ $dv }}) ha pagado la
renovación de su certificado (Orden: {{ $orderUuid }}).

Por favor genere un nuevo certificado con vigencia hasta {{ $newExpiration }}
y envíelo al cliente.

-- Sistema Certificate Manager
```

---



## 6. Mejoras Identificadas

### 6.1 KYC Link — Falta persistencia en BD

**Gap:** El link KYC se obtiene bajo demanda en cada llamada al frontend; no se guarda.  
**Impacto:** Si Viafirma cambia el estado a otro (`accreditation_check`, etc.) antes de que el cliente abra el link, la API retorna 400 y el link ya no es recuperable.  
**Mejora:** Añadir campo `kyc_accreditation_link VARCHAR(500) NULL` en `viafirma_certificate_request_states`. Cuando `StateMachine` detecta la transición a `remote_status = 'accreditation'`, llamar automáticamente a `/services/accreditation/{codRequest}` y persistir el link.

```sql
-- Migración sugerida
ALTER TABLE viafirma_certificate_request_states
    ADD COLUMN kyc_accreditation_link VARCHAR(500) NULL
    COMMENT 'Link del portal KYC de acreditación — disponible en estado accreditation'
    AFTER revocation_request_code;
```

### 6.2 `DownloadP7bJob` — Posible N+1 por falta de eager-load

**Gap:** El job carga la entidad con `find()` sin `with('state')`. La propiedad `internal_state` hace proxy vía accesor que ejecuta una segunda query `$this->state?->internal_state`.  
**Mejora:** Cambiar línea 62:
```php
// Antes:
$entity = ViafirmaCertificateRequest::find($this->requestId);
// Después:
$entity = ViafirmaCertificateRequest::with('state')->find($this->requestId);
```

### 6.3 `GetKycLinkUseCase` — Lee `remote_status` del modelo raíz, no del estado

**Gap:** `$entity->remote_status` es leído directamente del modelo `ViafirmaCertificateRequest`, pero tras la normalización ese campo vive en `viafirma_certificate_request_states`. El modelo raíz no tiene ese accesor.  
**Impacto:** La validación `RemoteStatus::tryFrom($entity->remote_status)` retorna siempre `null` → lanza excepción falsa para solicitudes válidas en estado `accreditation`.  
**Mejora:**
```php
// Antes:
$remoteStatus = RemoteStatus::tryFrom((string) $entity->remote_status);
// Después:
$entity = ViafirmaCertificateRequest::with('state')->findOrFail($id);
$remoteStatus = RemoteStatus::tryFrom((string) $entity->state?->remote_status);
```

### 6.4 `ReviveStalledViafirmaPollsJob` — No registra en `change_histories`

**Gap:** Cuando el watchdog revive un poll huérfano, no hay rastro en el historial legible por el administrador.  
**Mejora:** Al despachar el poll de recuperación, agregar:
```php
ChangeHistory::create([
    'certificate_request_id' => $stateRecord->viafirmaCertificateRequest->certificate_request_id,
    'status'                 => CertificateRequestStatusEnum::PROCESSING->value,
    'comments'               => 'Sistema: poll de estado reiniciado automáticamente por watchdog.',
    'user_of_change'         => 'SYSTEM',
]);
```

### 6.5 `AssembleP12Job` — Errores transitorios van a `FAILED` (irrecuperable)

**Gap:** El `catch(\Throwable)` genérico hace que errores de red (S3 timeout, fallo del vault) queden como `FAILED` con `key_vault_ref = 'PURGED'`. La llave privada se pierde y el trámite no puede recuperarse.  
**Mejora:** Diferenciar `TransientHttpException` / `StorageException` y mapearlas a `FAILED_RECOVERABLE` sin purgar la llave, para que `AutoRedownloadPendingViafirmaJob` pueda rescatarlas.

### 6.6 `company.issuance_provider` no está en `$fillable`

**Gap:** El campo existe en la migración `2026_05_19_180000_add_issuance_provider_to_companies_table.php` pero no está en `$fillable` del modelo `Company.php`.  
**Impacto:** No se puede actualizar via mass-assignment desde el panel de administración.  
**Mejora:** Agregar `'issuance_provider'` al array `$fillable` del modelo `Company`.

### 6.7 `RenewCertificateUseCase` — No diferencia por proveedor

**Gap (Crítico):** Descrito en §5.  
**Mejora:** Crear un `RenewalOrchestrator` que seleccione la estrategia:
- **email** → actualiza BD + envía correo al administrador para gestión manual.
- **viafirma** → crea nueva `CertificateRequest` clonada + dispara nueva emisión Viafirma automáticamente.

### 6.8 Notificación al cliente cuando llega a estado `accreditation`

**Gap:** El cliente no sabe que debe completar el proceso KYC hasta que lo busca en el panel o el soporte lo contacta.  
**Mejora:** Cuando `StateMachine::dispatchDomainEvents()` detecta `remote_status = 'accreditation'`, disparar un evento `ViafirmaAccreditationRequired` con listener que envíe un correo al `legal_rep_email` con el link KYC.

---

## 7. Tablas de Auditoría — Resumen por evento

### `viafirma_status_history`

| Quién escribe | Transición | Cuándo |
|---|---|---|
| `StateMachine::recordHistory()` | Cualquier cambio de `internal_state` o `remote_status` | En cada poll exitoso |
| `IssueCertificateUseCase` | `DRAFT → SUBMITTED` | Al emitir CSR |
| `AssembleP12Job` | `DOWNLOADED → ASSEMBLED` / `ASSEMBLED → COMPLETED` | Al ensamblar P12 |
| `RedownloadCertificateUseCase` | `{prev} → ASSEMBLED` | Al re-descargar admin |
| `StateMachine::markFailed()` | `{prev} → FAILED` | Timeout/circuito abierto |
| `StateMachine::markExpired()` | `{prev} → EXPIRED` | SLA 72h superado |

### `change_histories`

| Quién escribe | `status` | Contexto |
|---|---|---|
| `CertificateRequestMailService` | `PROCESSING` | Al enviar correo (flujo email) |
| `IssueCertificateUseCase` | `PROCESSING` | Al enviar CSR a Viafirma |
| `DownloadP7bJob` | `PROCESSING` | Al descargar P7B |
| `AssembleP12Job` (éxito) | `PROCESSED` | Al finalizar P12 |
| `AssembleP12Job` (error) | `PROCESSING` | Al fallar ensamblado |
| `RedownloadCertificateUseCase` | `PROCESSED` | Al re-descargar admin |
| `PaymentOrchestrator` (renovación) | N/A | Sólo `comments` con UUID de la orden |
| `RevokeCertificateUseCase` | `REVOKED` | Al revocar |

---

## 8. Próximos Pasos Propuestos (Backlog)

| Prioridad | Tarea | Archivos afectados | Estado |
|---|---|---|---|
| 🔴 Alta | Corregir lectura de `remote_status` en `GetKycLinkUseCase` (lee del modelo raíz, no del estado) | `GetKycLinkUseCase.php` | ✅ IMPLEMENTADO |
| 🔴 Alta | Renovación EMAIL: crear nueva solicitud (pendiente para sprint futuro) | `PaymentOrchestrator.php`, `RenewCertificateUseCase.php` | 📋 PENDIENTE (requiere nueva solicitud, no extensión) |
| 🟡 Media | Agregar eager-load `with('state')` en `DownloadP7bJob` | `DownloadP7bJob.php` | ✅ IMPLEMENTADO |
| 🟡 Media | Persistir KYC link en BD al detectar estado `accreditation` | `StateMachine.php`, migración nueva | ⏳ A implementar |
| 🟡 Media | Notificar al cliente cuando llega a estado `accreditation` | Nuevo listener `SendKycNotificationListener` | ⏳ A implementar |
| 🟡 Media | Registrar en `change_histories` al revivir polls huérfanos | `ReviveStalledViafirmaPollsJob.php` | ✅ IMPLEMENTADO |
| 🟢 Baja | Añadir `issuance_provider` a `$fillable` en `Company.php` | `Company.php` | ✅ IMPLEMENTADO |
| 🟢 Baja | Diferenciar errores transitorios en `AssembleP12Job` → `FAILED_RECOVERABLE` | `AssembleP12Job.php` | ⏳ A implementar |
| 🟢 Baja | Implementar botón "Renovar" en el frontend Angular | Frontend (varios archivos) | ⏳ A implementar |

---

## 9. Cambios Concretos a Implementar (Sesión Actual)

### 9.1 GetKycLinkUseCase.php — Línea 28-31

**Problema:** Lee `remote_status` del modelo raíz (`$entity->remote_status`), pero tras la normalización ese campo vive en `viafirma_certificate_request_states`.

**Cambio:**
```php
// ANTES (línea 28-31):
$entity = ViafirmaCertificateRequest::findOrFail($viafirmaCertificateRequestId);
$remoteStatus = RemoteStatus::tryFrom((string) $entity->remote_status);

// DESPUÉS:
$entity = ViafirmaCertificateRequest::with('state')->findOrFail($viafirmaCertificateRequestId);
$remoteStatus = RemoteStatus::tryFrom((string) $entity->state?->remote_status);
```

**Impacto:** Corrige excepciones falsas para solicitudes válidas en estado `accreditation`.

---

### 9.2 DownloadP7bJob.php — Línea 62

**Problema:** Falta eager-load `with('state')`, causando N+1 en acceso a `internal_state`.

**Cambio:**
```php
// ANTES (línea 62):
$entity = ViafirmaCertificateRequest::find($this->requestId);

// DESPUÉS:
$entity = ViafirmaCertificateRequest::with('state')->find($this->requestId);
```

**Impacto:** Elimina query extra por cada acceso a `internal_state`.

---

### 9.3 Company.php — Línea 41-45

**Problema:** `issuance_provider` no está en `$fillable`, bloqueando actualizaciones desde panel admin.

**Cambio:**
```php
// ANTES (línea 41-45):
protected $fillable = [
    'country_id', 'city_id', 'identity_document_id', 'type_organization_id',
    'company_name', 'dni', 'dv', 'address', 'city_name',
    'location', 'postal_code',  'phone', 'email',  'image', 'active', 'uuid'
];

// DESPUÉS:
protected $fillable = [
    'country_id', 'city_id', 'identity_document_id', 'type_organization_id',
    'company_name', 'dni', 'dv', 'address', 'city_name',
    'location', 'postal_code',  'phone', 'email',  'image', 'active', 'uuid',
    'issuance_provider'
];
```

**Impacto:** Permite actualizar el proveedor de emisión vía mass-assignment.

---

### 9.4 ReviveStalledViafirmaPollsJob.php — Agregar registro en change_histories

**Problema:** No hay rastro en historial legible cuando el watchdog revive un poll huérfano.

**Cambio:** Después de despachar el poll de recuperación, agregar:
```php
ChangeHistory::create([
    'certificate_request_id' => $stateRecord->viafirmaCertificateRequest->certificate_request_id,
    'status'                 => CertificateRequestStatusEnum::PROCESSING->value,
    'comments'               => 'Sistema: poll de estado reiniciado automáticamente por watchdog.',
    'user_of_change'         => 'SYSTEM',
]);
```

**Impacto:** Proporciona trazabilidad de recuperaciones automáticas.

---

### 9.5 PaymentOrchestrator.php — Diferenciar por proveedor en renovación

**Problema:** No diferencia entre `email` y `viafirma` al procesar renovación. Para `email`, nadie es notificado.

**Cambio:** En `processCertificateRenewal()`, después de actualizar `certificate_requests`, agregar:
```php
$provider = $cr->company?->issuance_provider ?? 'mail';
if ($provider === 'mail') {
    $receiptEmail = config('certificate.mail.receipt_email');
    \Illuminate\Support\Facades\Mail::to($receiptEmail)->queue(
        new \App\Mail\CertificateRenewalAdminNotification($cr, $order)
    );
}
```

**Archivos nuevos requeridos:**
- `app/Mail/CertificateRenewalAdminNotification.php` (Mailable)
- `resources/views/emails/certificate_renewal_admin.blade.php` (Template)

**Impacto:** Notifica al administrador cuando se paga renovación en flujo `email`.


---

## Notas Clave del Modelo de Negocio

> **Viafirma siempre emite P12 por 2 años.** El cliente elige 1 o 2 años comercialmente. Si elige 1 año, el P12 ya tiene 2 años grabados pero `expiration_date` en BD refleja solo 1. El `/renew` desbloquea el segundo año comercial actualizando solo `certificate_requests` — **no se re-emite el P12**.
>
> **Para el flujo email**, la CA sí emite físicamente por 1 año. La renovación implica gestión manual: el administrador debe solicitar a la CA un nuevo certificado y entregárselo al cliente.

