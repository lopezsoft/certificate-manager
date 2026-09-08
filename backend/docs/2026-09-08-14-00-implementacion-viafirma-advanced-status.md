# Implementación: `GET /request/{codRequest}/advancedStatus` (Viafirma)

> Estado: **DISEÑO CONFIRMADO — PENDIENTE DE DISPONIBILIDAD EN SANDBOX**. Viafirma (Benito Galán) propuso el endpoint el 2026-09-08 y respondió todas las preguntas de diseño el mismo día. Falta que lo publiquen en su Sandbox (nos avisan cuando esté) antes de implementar y probar contra un entorno real. Benito estima poder avisar **la semana del 2026-09-14**.

## 1. Motivación

Hoy solo conocemos el estado remoto vía `GET /request/{cod}/status`, que retorna un código genérico (`collate_data`, `checking`, `rues_error`, etc.). Cuando el trámite queda bloqueado, `StateMachine::buildErrorMessage()` construye un mensaje **prescriptivo genérico por estado** (ver `app/Modules/Viafirma/Domain/StateMachine.php:260`), no la razón real del rechazo.

`advancedStatus` trae `requestPublicNotes` — notas específicas y legibles ya persistidas en la plataforma de Viafirma. Ejemplo real compartido por Benito (solicitud `A152B9Q7L`):

```
"El nombre no coincide, debe introducir su nombre tal cual aparece en su documento"
"Los apellidos no coinciden, debe introducir sus apellidos tal cual aparecen en su documento"
"La identificación no coincide"
"Los datos de su solicitud coinciden con los de su acreditación"
"Certificado emitido."
"Firma completada"
```

Esto permite reemplazar nuestros mensajes genéricos por el motivo exacto que ve el usuario final — mejora de UX y reducción de soporte.

## 2. Contrato confirmado por Viafirma

```
GET /request/{codRequest}/advancedStatus
```

```json
{
    "status": "collate_data",
    "accredited": "NOT_MATCHING",
    "paid": "NOT_STARTED",
    "requestPublicNotes": [
        { "note": "El nombre no coincide", "date": 1788551700243 }
    ]
}
```

| Campo | Descripción |
|---|---|
| `status` | Estado actual de la solicitud — equivalente al `code` de `/status` actual. Viafirma confirmó que podemos migrar todo el polling a este endpoint. |
| `accredited` | Estado del proceso de acreditación KYC. **Catálogo completo confirmado** (sección 2.1). |
| `paid` | Estado del pago (perfiles con flujo de pago automático). **Para nuestros perfiles (FE-PJ/FE-PN) siempre será `NOT_STARTED`** — Benito confirmó que podemos omitirlo en nuestra UI, solo aplica a otros integradores con pago automatizado. Lo guardamos igual por completitud/futuro, sin construirle lógica encima. |
| `requestPublicNotes[].note` | Texto de la nota, visible para usuario y operador RA. |
| `requestPublicNotes[].date` | Timestamp epoch en **milisegundos** (confirmado). |

### 2.1 Catálogo confirmado de `accredited`

| Valor | Significado |
|---|---|
| `NOT_STARTED` | Acreditación aún no iniciada |
| `PROCESSING` | Iniciando (estado muy fugaz) |
| `PREVERIFIED` | KYC salió OK |
| `REJECTED` | KYC salió KO |
| `REVIEW` | Requiere revisión de operador RA |
| `NOT_MATCHING` | Datos extraídos por KYC no coinciden con los esperados por la RA (ej. nombre registrado vs. nombre en el documento) |
| `COMPLETED` | Datos coinciden entre KYC y RA, y ya se descargaron las evidencias (fotos, selfie, video, etc.) |
| `VERIFIED` | **Deprecado.** Solo puede aparecer en solicitudes ya completadas antes del cambio de Viafirma — ninguna solicitud nueva lo tendrá. Se debe soportar en el parseo pero no como estado activo esperado. |

## 3. Diseño de integración

### 3.1 Contrato y DTOs
- Nuevo método en `ViafirmaClient` (`app/Modules/Viafirma/Domain/Contracts/ViafirmaClient.php`): `getAdvancedStatus(string $codRequest): AdvancedStatusResultDto`.
- Nuevo enum `AccreditedStatus` (junto a `RemoteStatus`):
  ```php
  enum AccreditedStatus: string
  {
      case NOT_STARTED  = 'NOT_STARTED';
      case PROCESSING   = 'PROCESSING';
      case PREVERIFIED  = 'PREVERIFIED';
      case REJECTED     = 'REJECTED';
      case REVIEW       = 'REVIEW';
      case NOT_MATCHING = 'NOT_MATCHING';
      case COMPLETED    = 'COMPLETED';
      // Deprecado por Viafirma — solo en solicitudes ya completadas antes del cambio.
      // No debe aparecer en solicitudes nuevas; se mantiene para no romper el parseo.
      case VERIFIED     = 'VERIFIED';
  }
  ```
- Nuevo DTO `AdvancedStatusResultDto`:
  ```php
  final class AdvancedStatusResultDto
  {
      public function __construct(
          public readonly RemoteStatus $status,
          public readonly ?AccreditedStatus $accredited,
          public readonly ?string $paid, // sin enum propio — ver 2. tabla, no aplica a nuestros perfiles
          /** @var PublicNoteDto[] */
          public readonly array $publicNotes,
          public readonly array $raw = [],
      ) {}
  }

  final class PublicNoteDto
  {
      public function __construct(
          public readonly string $note,
          public readonly \Illuminate\Support\Carbon $occurredAt, // desde epoch millis
      ) {}
  }
  ```
- Igual que con `RemoteStatus`, `AccreditedStatus::tryFrom()` debe manejar valores desconocidos con un `warning` en log, no una excepción — mismo criterio aplicado tras el incidente de `collate_data` no mapeado.
- Implementar en `GuzzleViafirmaClient` (llamada real) y `MockViafirmaClient` (simular notas acumulativas por poll, igual que ya simula `rues_check → accreditation → inProcess`).

### 3.2 Migración completa del polling — CONFIRMADO
Benito confirmó: **migrar `PollViafirmaStatusJob` a `getAdvancedStatus()`** en una sola llamada. `/status` **no se deprecará** (lo siguen usando otros integradores), pero nosotros dejamos de usarlo una vez migremos — no hay necesidad de mantener ambas llamadas.

### 3.3 Persistencia de notas — nueva tabla (dedup ya validado)
Benito confirmó: **`requestPublicNotes` es acumulativo** — cada poll trae el historial completo hasta ese momento (hoy 2 notas, mañana 6). Esto valida el diseño original de deduplicación por clave única, sin cambios:

```sql
CREATE TABLE viafirma_request_public_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    viafirma_certificate_request_id BIGINT UNSIGNED NOT NULL,
    note VARCHAR(500) NOT NULL,
    occurred_at DATETIME NOT NULL,
    created_at TIMESTAMP NULL,
    CONSTRAINT fk_viafirma_public_notes_request
        FOREIGN KEY (viafirma_certificate_request_id)
        REFERENCES viafirma_certificate_requests(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_note_dedupe (viafirma_certificate_request_id, note, occurred_at)
);
```

Como la respuesta trae el arreglo completo en cada poll, la inserción debe ser `INSERT IGNORE` (o `upsert` sin actualizar) por cada nota del arreglo — las ya vistas colisionan con la `UNIQUE KEY` y se ignoran silenciosamente; solo las nuevas (agregadas por Viafirma desde el último poll) se insertan.

### 3.4 Exposición al endpoint `/issuance`
Agregar a `ViafirmaIssuanceProvider::status()` (`app/Services/Certificate/Providers/ViafirmaIssuanceProvider.php`):
- `accredited_status` (del campo `accredited`)
- `last_public_note` (la nota más reciente por `occurred_at`, para no romper compatibilidad con `last_error_message` que ya se consume)
- `public_notes` (arreglo completo, para que el frontend pueda mostrar el historial si lo necesita)

`paid` **no se expone** en `/issuance` — no aplica a nuestros perfiles (confirmado por Viafirma), se persiste solo por completitud si algún día cambia.

### 3.5 Retrocompatibilidad — CONFIRMADO
Benito confirmó: el endpoint aplica a **todas** las solicitudes, incluidas las creadas antes de su publicación — devuelve el estado actual desde la data ya persistida en Viafirma. No hace falta lógica de "solo para solicitudes nuevas".

## 4. Impacto en código existente

| Archivo | Cambio |
|---|---|
| `app/Modules/Viafirma/Domain/Contracts/ViafirmaClient.php` | + método `getAdvancedStatus()` |
| `app/Modules/Viafirma/Infrastructure/Http/GuzzleViafirmaClient.php` | + implementación real |
| `app/Modules/Viafirma/Infrastructure/Http/MockViafirmaClient.php` | + simulación de notas acumulativas por poll |
| `app/Modules/Viafirma/Domain/Enums/AccreditedStatus.php` | Nuevo enum (8 valores, ver 2.1) |
| `app/Modules/Viafirma/Application/DTOs/AdvancedStatusResultDto.php` | Nuevo |
| `app/Modules/Viafirma/Application/DTOs/PublicNoteDto.php` | Nuevo |
| `app/Modules/Viafirma/Infrastructure/Jobs/PollViafirmaStatusJob.php` | Migrar `getStatus()` → `getAdvancedStatus()`, persistir notas nuevas (upsert-ignore) |
| `app/Modules/Viafirma/Infrastructure/Persistence/Models/ViafirmaRequestPublicNote.php` | Nuevo modelo |
| `database/migrations/viafirma/` | Nueva migración + DDL manual (no ejecutar sin autorización, según política del proyecto) |
| `app/Services/Certificate/Providers/ViafirmaIssuanceProvider.php` | Exponer `accredited_status`, `last_public_note`, `public_notes` en `/issuance` |
| `app/Http/Controllers/SwaggerDefinitions.php` | Documentar los campos nuevos |

## 5. Preguntas resueltas (2026-09-08, respuesta de Benito Galán)

1. **Catálogo `accredited`/`paid`:** confirmado, ver sección 2.1. `paid` siempre `NOT_STARTED` para nuestros perfiles.
2. **¿Reemplaza `/status`?** Sí, migrar. `/status` sigue disponible para otros integradores, pero nosotros migramos por completo.
3. **`requestPublicNotes` acumulativo:** confirmado — siempre trae el historial completo hasta la fecha de consulta.
4. **Formato `date`:** confirmado, epoch en milisegundos.
5. **Retroactividad:** confirmado — aplica a todas las solicitudes, incluidas las ya en curso.
6. **Sandbox:** confirmado — Viafirma avisará apenas lo publiquen ahí.

## 6. Plan de rollout

1. ~~Confirmar respuestas de Viafirma~~ ✅ hecho (2026-09-08).
2. **Esperar aviso de Viafirma de publicación en Sandbox** (bloqueante actual).
3. Implementar y probar contra Sandbox de Viafirma.
4. Migración/DDL de `viafirma_request_public_notes` — **no ejecutar en prod sin aprobación explícita**, seguir la política del proyecto (migración creada pero no aplicada + DDL manual para ejecución supervisada).
5. Migrar `PollViafirmaStatusJob` de `getStatus()` a `getAdvancedStatus()`, actualizar `MockViafirmaClient`.
6. Exponer campos nuevos en `/issuance` + Swagger.
7. Tests unitarios (mockeados, sin BD) para el nuevo DTO, el parseo de notas, el enum `AccreditedStatus` (incluyendo el caso deprecado `VERIFIED`), y la deduplicación de notas.
8. Deploy + smoke test con una solicitud real antes de considerar cerrado.
