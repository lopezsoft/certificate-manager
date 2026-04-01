# Integración de Webhooks — Guía para el SPA

> **Audiencia:** Agente o desarrollador frontend encargado de la interfaz de gestión de webhooks.
> **Backend:** Laravel 10 + Passport (Bearer token)
> **Base URL:** `/api/v1`
> **Autenticación:** Todos los endpoints requieren header `Authorization: Bearer {token}`

---

## Tabla de Contenidos

1. [Conceptos clave](#1-conceptos-clave)
2. [Flujo general de la UI](#2-flujo-general-de-la-ui)
3. [Endpoints disponibles](#3-endpoints-disponibles)
4. [Tipos de evento](#4-tipos-de-evento)
5. [Respuestas del backend](#5-respuestas-del-backend)
6. [Reglas de negocio importantes](#6-reglas-de-negocio-importantes)
7. [Manejo del secret](#7-manejo-del-secret)
8. [Historial de entregas](#8-historial-de-entregas)
9. [Payloads que recibe el endpoint externo](#9-payloads-que-recibe-el-endpoint-externo)
10. [Firma HMAC — para mostrar al usuario](#10-firma-hmac--para-mostrar-al-usuario)
11. [Estados posibles](#11-estados-posibles)
12. [Errores comunes](#12-errores-comunes)

---

## 1. Conceptos clave

- **Webhook endpoint:** URL externa que el sistema llamará cuando ocurra un evento.
- **Evento:** Acción dentro del sistema que dispara la llamada (ej. cambio de estado de un certificado).
- **Secret:** Clave HMAC usada para firmar cada entrega. **Solo se muestra una vez** al crear o rotar.
- **Delivery:** Registro de cada intento de entrega a un endpoint externo.
- **failure_count:** El backend auto-desactiva un endpoint tras 10 fallos consecutivos.

---

## 2. Flujo general de la UI

```
[Sección "Webhooks"]
    │
    ├── Listar webhooks de la compañía
    │       GET /api/v1/webhooks
    │
    ├── Crear webhook
    │       POST /api/v1/webhooks
    │       → Mostrar secret al usuario (única vez)
    │
    ├── Editar webhook (url, eventos, activo/inactivo)
    │       PUT /api/v1/webhooks/{id}
    │
    ├── Rotar secret
    │       POST /api/v1/webhooks/{id}/rotate-secret
    │       → Mostrar nuevo secret al usuario (única vez)
    │
    ├── Eliminar webhook
    │       DELETE /api/v1/webhooks/{id}
    │
    └── Ver historial de entregas
            GET /api/v1/webhooks/{id}/deliveries
```

---

## 3. Endpoints disponibles

| Método | Ruta | Acción |
|--------|------|--------|
| `GET` | `/webhooks/events` | Obtener lista de eventos disponibles para suscribir |
| `GET` | `/webhooks` | Listar todos los webhooks de la compañía |
| `POST` | `/webhooks` | Crear nuevo webhook |
| `GET` | `/webhooks/{id}` | Ver detalle de un webhook |
| `PUT` | `/webhooks/{id}` | Actualizar webhook |
| `DELETE` | `/webhooks/{id}` | Eliminar webhook |
| `POST` | `/webhooks/{id}/rotate-secret` | Generar nuevo secret |
| `GET` | `/webhooks/{id}/deliveries` | Historial de entregas paginado |

---

## 4. Tipos de evento

Obtenerlos dinámicamente desde el backend:

**`GET /api/v1/webhooks/events`**

```json
{
    "data": [
        "certificate_request.created",
        "certificate_request.status_changed",
        "certificate_request.ai_processed",
        "certificate_request.file_uploaded",
        "certificate_request.deleted",
        "certificate.expiring"
    ]
}
```

Usar esta respuesta para construir el selector de eventos en el formulario de creación/edición, no hardcodear los valores.

---

## 5. Respuestas del backend

### `GET /api/v1/webhooks` — Lista de webhooks

```json
{
    "data": [
        {
            "id": 1,
            "company_id": 7,
            "url": "https://mi-erp.com/webhook",
            "events": [
                "certificate_request.created",
                "certificate_request.status_changed"
            ],
            "is_active": true,
            "description": "Notificaciones al ERP",
            "failure_count": 0,
            "last_triggered_at": "2026-02-19T10:30:00.000000Z",
            "created_at": "2026-02-19T08:00:00.000000Z",
            "updated_at": "2026-02-19T10:30:00.000000Z"
        }
    ]
}
```

> El campo `secret` **nunca** aparece en esta respuesta. Solo se expone en `POST /webhooks` y `POST /webhooks/{id}/rotate-secret`.

---

### `POST /api/v1/webhooks` — Crear webhook

**Body:**
```json
{
    "url": "https://mi-erp.com/webhook",
    "events": ["certificate_request.created", "certificate_request.status_changed"],
    "description": "Notificaciones al ERP"
}
```

**Respuesta `201`:**
```json
{
    "data": {
        "id": 1,
        "company_id": 7,
        "url": "https://mi-erp.com/webhook",
        "events": ["certificate_request.created", "certificate_request.status_changed"],
        "is_active": true,
        "description": "Notificaciones al ERP",
        "failure_count": 0,
        "last_triggered_at": null,
        "created_at": "2026-02-19T08:00:00.000000Z",
        "updated_at": "2026-02-19T08:00:00.000000Z"
    }
}
```

> **Nota importante:** La respuesta de creación **NO incluye el secret**. El secret se obtiene únicamente con `rotate-secret`. Al crear un webhook, se debe guiar al usuario a ejecutar rotate-secret de inmediato para obtener y guardar el secret.

---

### `GET /api/v1/webhooks/{id}` — Detalle de webhook

```json
{
    "data": {
        "id": 1,
        "company_id": 7,
        "url": "https://mi-erp.com/webhook",
        "events": ["certificate_request.created"],
        "is_active": true,
        "description": "Notificaciones al ERP",
        "failure_count": 2,
        "last_triggered_at": "2026-02-19T10:30:00.000000Z",
        "created_at": "2026-02-19T08:00:00.000000Z",
        "updated_at": "2026-02-19T10:30:00.000000Z"
    }
}
```

---

### `PUT /api/v1/webhooks/{id}` — Actualizar webhook

Todos los campos son opcionales. Enviar solo los campos a cambiar.

**Body (ejemplo — desactivar y cambiar URL):**
```json
{
    "url": "https://nuevo-dominio.com/webhook",
    "is_active": false
}
```

**Respuesta `200`:**
```json
{
    "data": {
        "id": 1,
        "company_id": 7,
        "url": "https://nuevo-dominio.com/webhook",
        "events": ["certificate_request.created"],
        "is_active": false,
        "description": "Notificaciones al ERP",
        "failure_count": 2,
        "last_triggered_at": "2026-02-19T10:30:00.000000Z",
        "created_at": "2026-02-19T08:00:00.000000Z",
        "updated_at": "2026-02-19T11:00:00.000000Z"
    }
}
```

---

### `DELETE /api/v1/webhooks/{id}` — Eliminar webhook

**Respuesta `204`:** Sin body.

---

### `POST /api/v1/webhooks/{id}/rotate-secret` — Rotar secret

**No requiere body.**

**Respuesta `200`:**
```json
{
    "data": {
        "id": 1,
        "company_id": 7,
        "url": "https://mi-erp.com/webhook",
        "events": ["certificate_request.created"],
        "is_active": true,
        "description": "Notificaciones al ERP",
        "failure_count": 0,
        "last_triggered_at": null,
        "created_at": "2026-02-19T08:00:00.000000Z",
        "updated_at": "2026-02-19T12:00:00.000000Z"
    },
    "secret": "xK9mP2qL7nR4tY1uI8oP3aS6dF0gH5jK2mN8vB"
}
```

> **El campo `secret` solo aparece aquí.** Presentar al usuario con instrucción clara de copiarlo y guardarlo. Una vez cerrado el modal/pantalla, no se puede recuperar.

---

### `GET /api/v1/webhooks/{id}/deliveries` — Historial de entregas

**Query params:** `?limit=20` (default 20)

**Respuesta `200`:**
```json
{
    "data": {
        "data": [
            {
                "id": 42,
                "webhook_endpoint_id": 1,
                "event_type": "certificate_request.status_changed",
                "payload": {
                    "id": "wh_01HXYZ123ABC",
                    "event": "certificate_request.status_changed",
                    "created_at": "2026-02-19T10:30:00+00:00",
                    "data": {
                        "certificate_request_id": 15,
                        "company_id": 7,
                        "previous_status": "PENDING",
                        "new_status": "ACCEPTED",
                        "changed_by_user_id": 3,
                        "comment": "Documentación verificada"
                    }
                },
                "http_status": 200,
                "response_body": "{\"ok\":true}",
                "status": "delivered",
                "attempt": 1,
                "delivered_at": "2026-02-19T10:30:01.000000Z",
                "created_at": "2026-02-19T10:30:00.000000Z",
                "updated_at": "2026-02-19T10:30:01.000000Z"
            },
            {
                "id": 41,
                "webhook_endpoint_id": 1,
                "event_type": "certificate_request.created",
                "payload": { "..." : "..." },
                "http_status": 500,
                "response_body": "Internal Server Error",
                "status": "failed",
                "attempt": 3,
                "delivered_at": null,
                "created_at": "2026-02-19T09:00:00.000000Z",
                "updated_at": "2026-02-19T09:16:00.000000Z"
            }
        ],
        "current_page": 1,
        "last_page": 3,
        "per_page": 20,
        "total": 58
    }
}
```

---

## 6. Reglas de negocio importantes

| Regla | Valor | Campo donde se refleja |
|-------|-------|----------------------|
| Máximo webhooks por compañía | 5 | Error `422` al crear el 6to |
| Tamaño máximo de URL | 500 chars | Validación en crear/editar |
| Mínimo 1 evento suscrito | 1 | Validación en crear/editar |
| Auto-desactivación por fallos | 10 fallos consecutivos | `is_active → false`, `failure_count` |
| Timeout de entrega | 10 segundos | — |
| Reintentos automáticos | 3 (backoff: 1min, 5min, 15min) | `attempt` en delivery |

### Indicador de salud del webhook

Usar `failure_count` e `is_active` para mostrar un badge de estado:

- `is_active: false` + `failure_count >= 10` → **Auto-desactivado por fallos** (badge rojo)
- `is_active: false` (manual) → **Pausado** (badge amarillo)
- `is_active: true` + `failure_count > 0` → **Activo con advertencias** (badge naranja)
- `is_active: true` + `failure_count === 0` → **Activo** (badge verde)

---

## 7. Manejo del secret

### Flujo recomendado en la UI

```
[Crear webhook]
      ↓
Respuesta 201 (sin secret)
      ↓
Mostrar modal: "Tu webhook fue creado. Para obtener el secret, haz clic en 'Obtener secret'"
      ↓
[Botón: Obtener secret] → POST /webhooks/{id}/rotate-secret
      ↓
Mostrar secret en input readonly con botón "Copiar"
+ Advertencia: "Este secret no se volverá a mostrar. Guárdalo en un lugar seguro."
```

### Al rotar el secret

- Confirmar con el usuario antes de ejecutar (el anterior queda invalidado inmediatamente).
- Mostrar el nuevo secret de la misma forma que en la creación.
- Informar que el sistema receptor deberá actualizar su configuración.

---

## 8. Historial de entregas

### Columnas sugeridas para la tabla

| Campo | Descripción |
|-------|-------------|
| `id` | ID de la entrega |
| `event_type` | Tipo de evento disparado |
| `status` | `delivered` / `failed` / `pending` |
| `http_status` | Código HTTP recibido del endpoint externo |
| `attempt` | Número de intento (1, 2 o 3) |
| `delivered_at` | Fecha/hora de entrega exitosa |
| `created_at` | Fecha/hora del primer intento |

### Estado badge para deliveries

- `delivered` → Verde
- `failed` → Rojo
- `pending` → Gris

### Ver payload completo

El campo `payload` contiene el JSON completo enviado. Mostrar en un modal/drawer con formato JSON resaltado.

---

## 9. Payloads que recibe el endpoint externo

Estos son los JSON exactos que el backend envía a la URL configurada en el webhook. Útiles para que el usuario sepa qué esperar y para mostrarlos como ejemplo en la UI.

### `certificate_request.created`

```json
{
    "id": "wh_01HXYZ123ABC",
    "event": "certificate_request.created",
    "created_at": "2026-02-19T08:00:00+00:00",
    "data": {
        "certificate_request_id": 42,
        "company_id": 7,
        "company_name": "MI EMPRESA S.A.S.",
        "dni": "900455420",
        "dv": 8,
        "request_status": "DRAFT",
        "legal_representative": "JUAN PÉREZ GÓMEZ"
    }
}
```

---

### `certificate_request.status_changed`

```json
{
    "id": "wh_01HXYZ456DEF",
    "event": "certificate_request.status_changed",
    "created_at": "2026-02-19T10:30:00+00:00",
    "data": {
        "certificate_request_id": 42,
        "company_id": 7,
        "previous_status": "PENDING",
        "new_status": "ACCEPTED",
        "changed_by_user_id": 3,
        "comment": "Documentación verificada"
    }
}
```

**Posibles transiciones de estado:**
`DRAFT` → `SENT` → `PENDING` → `ACCEPTED` → `PROCESSING` → `PROCESSED`
`PENDING` / `ACCEPTED` / `PROCESSING` → `REJECTED`

---

### `certificate_request.file_uploaded`

```json
{
    "id": "wh_01HXYZ789GHI",
    "event": "certificate_request.file_uploaded",
    "created_at": "2026-02-19T09:15:00+00:00",
    "data": {
        "certificate_request_id": 42,
        "company_id": 7,
        "file_id": 18,
        "file_name": "RUT-empresa.pdf",
        "document_type": "ATTACHED"
    }
}
```

---

### `certificate_request.ai_processed`

```json
{
    "id": "wh_01HXYZ012JKL",
    "event": "certificate_request.ai_processed",
    "created_at": "2026-02-19T09:20:00+00:00",
    "data": {
        "certificate_request_id": 42,
        "company_id": 7,
        "file_id": 18,
        "processing_time_ms": 4.32,
        "overall_valid": true,
        "document_type": "RUT"
    }
}
```

---

### `certificate_request.deleted`

```json
{
    "id": "wh_01HXYZ345MNO",
    "event": "certificate_request.deleted",
    "created_at": "2026-02-19T11:00:00+00:00",
    "data": {
        "certificate_request_id": 42,
        "company_id": 7,
        "dni": "900455420",
        "company_name": "MI EMPRESA S.A.S."
    }
}
```

---

### `certificate.expiring`

```json
{
    "id": "wh_01HXYZ678PQR",
    "event": "certificate.expiring",
    "created_at": "2026-02-19T07:00:00+00:00",
    "data": {
        "certificate_request_id": 42,
        "company_id": 7,
        "company_name": "MI EMPRESA S.A.S.",
        "dni": "900455420",
        "request_status": "PROCESSED"
    }
}
```

---

## 10. Firma HMAC — para mostrar al usuario

Cada entrega incluye el header `X-Webhook-Sig`. Mostrar esta información en la UI como referencia para que el sistema receptor pueda verificar la autenticidad.

### Headers que recibe el endpoint externo

```
POST https://mi-erp.com/webhook
Content-Type: application/json
X-Webhook-Sig: t=1708345200,v1=a4b2c8d1e5f3...
X-Webhook-Source: Certificate Manager
X-Webhook-Event: certificate_request.status_changed
```

### Formato del header `X-Webhook-Sig`

```
t={timestamp_unix},v1={hmac_sha256}
```

- `t` → timestamp Unix del momento de envío
- `v1` → HMAC-SHA256 del body JSON con el secret como clave
- El receptor debe verificar que `|tiempo_actual - t| < 300` segundos (protección replay)
- Comparar `v1` con `HMAC-SHA256(body, secret)` usando comparación en tiempo constante

---

## 11. Estados posibles

### Webhook endpoint (`is_active` + `failure_count`)

| `is_active` | `failure_count` | Interpretación |
|-------------|-----------------|----------------|
| `true` | `0` | Activo y saludable |
| `true` | `1–9` | Activo con fallos intermitentes |
| `false` | `>= 10` | Auto-desactivado por fallos repetidos |
| `false` | `< 10` | Desactivado manualmente |

### Delivery (`status`)

| Valor | Descripción |
|-------|-------------|
| `pending` | Entrega pendiente o en proceso |
| `delivered` | HTTP 2xx recibido del endpoint externo |
| `failed` | Agotados los 3 reintentos sin éxito |

---

## 12. Errores comunes

### `422` — Validación fallida

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "url": ["La URL del webhook no es válida."],
        "events": ["Debe seleccionar al menos un tipo de evento."]
    }
}
```

### `422` — Límite de webhooks alcanzado

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "url": ["Se ha alcanzado el límite de 5 webhooks por compañía."]
    }
}
```

### `422` — Tipo de evento inválido

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "events": ["Tipos de evento inválidos: certificate_request.unknown"]
    }
}
```

### `404` — Webhook no encontrado

```json
{
    "success": false,
    "message": "Webhook endpoint not found."
}
```

### `401` — No autenticado

```json
{
    "message": "Unauthenticated."
}
```
