# Implementación: `GET /request/{codRequest}/advancedStatus` (Viafirma)

> Estado: **PENDIENTE DE LUZ VERDE** — Viafirma (Benito Galán) propuso este endpoint el 2026-09-08. No implementar hasta confirmar las preguntas abiertas (sección 5) y recibir aprobación/disponibilidad real en su API.

## 1. Motivación

Hoy solo conocemos el estado remoto vía `GET /request/{cod}/status`, que retorna un código genérico (`collate_data`, `checking`, `rues_error`, etc.). Cuando el trámite queda bloqueado, `StateMachine::buildErrorMessage()` construye un mensaje **prescriptivo genérico por estado** (ver `app/Modules/Viafirma/Domain/StateMachine.php:260`), no la razón real del rechazo.

Viafirma propone `advancedStatus`, que además del estado trae `requestPublicNotes` — notas específicas y legibles ya persistidas en su plataforma. Ejemplo real compartido por Benito (solicitud `A152B9Q7L`):

```
"El nombre no coincide, debe introducir su nombre tal cual aparece en su documento"
"Los apellidos no coinciden, debe introducir sus apellidos tal cual aparecen en su documento"
"La identificación no coincide"
"Los datos de su solicitud coinciden con los de su acreditación"
"Certificado emitido."
"Firma completada"
```

Esto permite reemplazar nuestros mensajes genéricos por el motivo exacto que ve el usuario final — mejora de UX y reducción de soporte.

## 2. Contrato propuesto por Viafirma

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
| `status` | Estado actual de la solicitud — se asume equivalente al `code` de `/status` actual. |
| `accredited` | Estado del proceso de acreditación KYC (ej. `NOT_MATCHING`). Catálogo completo aún no confirmado por Viafirma. |
| `paid` | Estado del pago, para perfiles con flujo de pago automático (ej. `NOT_STARTED`). No aplica a los perfiles que usamos hoy (FE-PJ/FE-PN), a confirmar. |
| `requestPublicNotes[].note` | Texto de la nota, visible para usuario y operador RA. |
| `requestPublicNotes[].date` | Timestamp — se asume epoch en milisegundos (a confirmar). |

## 3. Diseño de integración propuesto

### 3.1 Contrato y DTOs
- Nuevo método en `ViafirmaClient` (`app/Modules/Viafirma/Domain/Contracts/ViafirmaClient.php`): `getAdvancedStatus(string $codRequest): AdvancedStatusResultDto`.
- Nuevo DTO `AdvancedStatusResultDto` (junto a `StatusResultDto` existente):
  ```php
  final class AdvancedStatusResultDto
  {
      public function __construct(
          public readonly RemoteStatus $status,
          public readonly ?string $accreditedStatus,
          public readonly ?string $paidStatus,
          /** @var PublicNoteDto[] */
          public readonly array $publicNotes,
          public readonly array $raw = [],
      ) {}
  }

  final class PublicNoteDto
  {
      public function __construct(
          public readonly string $note,
          public readonly \Illuminate\Support\Carbon $occurredAt,
      ) {}
  }
  ```
- Implementar en `GuzzleViafirmaClient` (llamada real) y `MockViafirmaClient` (simular notas de ejemplo por poll, igual que ya simula `rues_check → accreditation → inProcess`).

### 3.2 Reemplazo (no adición) del polling actual — **sujeto a confirmación de Viafirma (pregunta 5.2)**
Si `advancedStatus` retorna el mismo `status` que `/status` hoy, `PollViafirmaStatusJob::executePolling()` (línea ~147) debería **migrar** de `getStatus()` a `getAdvancedStatus()` en una sola llamada, evitando duplicar el polling HTTP. Si Viafirma confirma que son independientes, evaluar si vale la pena el costo de 2 llamadas por ciclo o esperar a que unifiquen.

### 3.3 Persistencia de notas — nueva tabla
Las notas no deben mezclarse con `viafirma_status_history` (esa tabla registra transiciones de *estado*, no mensajes). Se propone:

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

**Deduplicación:** dado que se hace polling cada 60s y `requestPublicNotes` probablemente repite notas ya vistas en cada respuesta (a confirmar, pregunta 5.3), el `UNIQUE KEY` sobre `(viafirma_certificate_request_id, note, occurred_at)` permite usar `INSERT IGNORE` / `upsert` sin duplicar filas, mismo patrón usado en `StateMachine::touchCurrentHistoryRow()` para el historial de estados.

### 3.4 Exposición al endpoint `/issuance`
Agregar a `ViafirmaIssuanceProvider::status()` (`app/Services/Certificate/Providers/ViafirmaIssuanceProvider.php`):
- `accredited_status` (del campo `accredited`)
- `last_public_note` (la nota más reciente, para no romper compatibilidad con `last_error_message` que ya se consume)
- Opcional: `public_notes` (arreglo completo) si el frontend quiere mostrar el historial completo, no solo la última.

### 3.5 Enum `accredited` / `paid`
No crear un enum estricto hasta tener el catálogo completo de Viafirma (pregunta 5.1) — evitar el mismo problema que tuvimos con `RemoteStatus` y los estados no contemplados (`collate_data`, etc., agregados reactivamente en agosto 2026). Mientras tanto, tratar `accredited`/`paid` como `string` libre y solo loguear valores desconocidos, sin lanzar excepción.

## 4. Impacto en código existente

| Archivo | Cambio |
|---|---|
| `app/Modules/Viafirma/Domain/Contracts/ViafirmaClient.php` | + método `getAdvancedStatus()` |
| `app/Modules/Viafirma/Infrastructure/Http/GuzzleViafirmaClient.php` | + implementación real |
| `app/Modules/Viafirma/Infrastructure/Http/MockViafirmaClient.php` | + simulación de notas por poll |
| `app/Modules/Viafirma/Application/DTOs/AdvancedStatusResultDto.php` | Nuevo |
| `app/Modules/Viafirma/Application/DTOs/PublicNoteDto.php` | Nuevo |
| `app/Modules/Viafirma/Infrastructure/Jobs/PollViafirmaStatusJob.php` | Migrar `getStatus()` → `getAdvancedStatus()`, persistir notas nuevas |
| `app/Modules/Viafirma/Infrastructure/Persistence/Models/ViafirmaRequestPublicNote.php` | Nuevo modelo |
| `database/migrations/viafirma/` | Nueva migración + DDL manual (no ejecutar sin autorización, según política del proyecto) |
| `app/Services/Certificate/Providers/ViafirmaIssuanceProvider.php` | Exponer `accredited_status` / `last_public_note` en `/issuance` |
| `app/Http/Controllers/SwaggerDefinitions.php` | Documentar los campos nuevos |

## 5. Preguntas abiertas — bloqueantes antes de implementar

1. Catálogo completo de valores de `accredited` y `paid`.
2. ¿`advancedStatus` reemplaza a `/status`, o hay que consultar ambos?
3. ¿`requestPublicNotes` trae el historial completo en cada respuesta, o solo notas nuevas/vigentes?
4. ¿`date` es epoch en milisegundos?
5. ¿Aplica a solicitudes ya en curso o solo a nuevas desde el lanzamiento?
6. ¿Disponible primero en sandbox de Viafirma para probar antes de producción?

## 6. Plan de rollout (una vez confirmado)

1. Confirmar respuestas de Viafirma (sección 5).
2. Implementar contra sandbox de Viafirma primero.
3. Migración/DDL de `viafirma_request_public_notes` — **no ejecutar en prod sin aprobación explícita**, seguir la política del proyecto (migración creada pero no aplicada + DDL manual para ejecución supervisada).
4. Actualizar `PollViafirmaStatusJob` y `MockViafirmaClient`.
5. Exponer campos nuevos en `/issuance` + Swagger.
6. Tests unitarios (mockeados, sin BD) para el nuevo DTO, el parseo de notas, y la deduplicación.
7. Deploy + smoke test con una solicitud real antes de considerar cerrado.
