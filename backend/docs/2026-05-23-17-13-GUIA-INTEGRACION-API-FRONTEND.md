# Guía de Integración API — Frontend

> **Base URL:** `http://cm-api.test/api/v1`
> **Autenticación:** Todas las rutas requieren header `Authorization: Bearer {token}`
> **Fecha:** 2026-05-23 17:13

---

## Flujo General

```
1. GET /quota/status
   └─ has_quota: true  → Mostrar "Crear Solicitud"
   └─ has_quota: false → Mostrar "Comprar Certificados"
                          └─ GET /pricing (ver tarifas)
                          └─ POST /orders (crear orden)
                          └─ POST /orders/{id}/pay (pagar con WOMPI)
                          └─ Webhook confirma → cupo disponible
                          └─ GET /quota/status (re-consultar)

2. POST /certificate-request
   └─ HTTP 200 → Solicitud creada exitosamente
   └─ HTTP 402 → Sin cupo, redirigir a compra
```

---

## 1. Consultar Disponibilidad de Cupo

```
GET /quota/status
```

**Respuesta (200):**

```json
{
  "success": true,
  "message": "Estado de cupos obtenido exitosamente",
  "dataRecords": {
    "has_quota": true,
    "prepaid_items_available": 5,
    "postpaid": {
      "allocated": 50,
      "used": 12,
      "remaining": 38,
      "expires_at": "2026-05-31",
      "status": "ACTIVE"
    }
  }
}
```

**Lógica frontend:**

- `has_quota === true` → habilitar formulario de creación.
- `has_quota === false` → mostrar mensaje "sin cupo" + botón "Comprar".
- Total disponible = `prepaid_items_available + (postpaid?.remaining ?? 0)`.

---

## 2. Consultar Tarifas

```
GET /pricing
```

**Respuesta (200):**

```json
{
  "success": true,
  "message": "Tarifas obtenidas exitosamente",
  "dataRecords": [
    { "tier": "RANGO_1", "min": 1,  "max": 4,    "price_1yr": 135000, "price_2yr": 215000 },
    { "tier": "RANGO_2", "min": 5,  "max": 9,    "price_1yr": 125000, "price_2yr": 200000 },
    { "tier": "RANGO_3", "min": 10, "max": null,  "price_1yr": 115000, "price_2yr": 185000 }
  ]
}
```

**Calcular precio exacto:**

```
GET /pricing?quantity=5&vigencia=1
```

```json
{
  "success": true,
  "message": "Precio calculado exitosamente",
  "dataRecords": {
    "tier": "RANGO_2",
    "unit_price": 125000,
    "quantity": 5,
    "vigencia": 1,
    "subtotal": 625000,
    "tax_amount": 118750,
    "total": 743750,
    "currency": "COP"
  }
}
```

---

## 3. Crear Orden de Compra

```
POST /orders
Content-Type: application/json
```

**Body:**

```json
{
  "quantity": 5,
  "vigencia": 1
}
```

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `quantity` | integer | Sí | Cantidad de certificados (≥ 1) |
| `vigencia` | integer | Sí | Vigencia en años: `1` o `2` |

**Respuesta (201):**

```json
{
  "success": true,
  "data": {
    "order_id": 42,
    "total_amount": 743750,
    "currency": "COP",
    "provider_reference": "ORD-a1b2c3d4e5f6",
    "acceptance_token": "eyJ...",
    "acceptance_url": "https://checkout.wompi.co/...",
    "integrity_hash": "abc123..."
  }
}
```

> **IMPORTANTE:** `acceptance_token`, `acceptance_url` e `integrity_hash` son necesarios para inicializar el widget de pago de WOMPI en el frontend.

---

## 4. Ejecutar Pago (WOMPI)

```
POST /orders/{id}/pay
Content-Type: application/json
```

**Body:**

```json
{
  "payment_source_id": 12345,
  "acceptance_token": "eyJ...",
  "payment_method": "CARD",
  "installments": 1
}
```

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `payment_source_id` | integer | Sí | ID del medio de pago registrado en WOMPI |
| `acceptance_token` | string | Sí | Token de aceptación devuelto por el widget |
| `payment_method` | string | Sí | Método: `CARD`, `NEQUI`, `PSE`, etc. |
| `installments` | integer | Sí | Número de cuotas (1 para pago de contado) |

**Respuesta (200):**

```json
{
  "success": true,
  "data": {
    "transaction_id": 99,
    "status": "PENDING",
    "provider_transaction_id": "..."
  }
}
```

**Flujo post-pago:**

1. El pago queda en estado `PENDING` mientras WOMPI procesa.
2. Hacer polling a `GET /orders/{id}` cada 5 segundos hasta que `status` cambie a `PAID`.
3. Cuando esté `PAID` → llamar `GET /quota/status` para refrescar cupos disponibles.

---

## 5. Crear Solicitud de Certificado

```
POST /certificate-request
Content-Type: multipart/form-data
```

**Body (form-data):**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `city_id` | integer | Sí | ID de la ciudad |
| `identity_document_id` | integer | Sí | Tipo de documento de identidad |
| `type_organization_id` | integer | Sí | Tipo de organización |
| `document_number` | string | Sí | Número de documento del representante |
| `address` | string | Sí | Dirección de la empresa |
| `legal_representative` | string | Sí | Nombre del representante legal |
| `company_name` | string | Sí | Razón social |
| `dni` | string | Sí | NIT de la empresa (sin DV) |
| `life` | integer | Sí | Vigencia del certificado (1 o 2 años) |
| `info` | string | No | Información adicional |
| `files[]` | File | Sí | Archivos adjuntos (mínimo 2, máximo 3) |

**Respuestas:**

| HTTP | Significado | Acción en el frontend |
|---|---|---|
| **200** | Solicitud creada exitosamente | Mostrar confirmación + actualizar listado |
| **400** | Validación fallida o solicitud duplicada | Mostrar mensaje del campo `message` |
| **402** | **Sin cupo disponible** | Redirigir al flujo de compra (paso 2) |
| **429** | Demasiadas solicitudes (throttle) | Mostrar "Intente nuevamente en un minuto" |

> **ADVERTENCIA:** Si recibes HTTP 402, significa que el cupo se agotó entre la consulta de `quota/status` y la creación. El frontend debe redirigir al usuario al flujo de compra.

---

## 6. Emisión del Certificado

Estos endpoints se usan después de que la solicitud fue aprobada.

### 6.1 Disparar emisión

```
POST /certificate-request/{id}/issue
Content-Type: application/json
```

**Body (opcional):**

```json
{
  "email_certificate": "usuario@empresa.com",
  "comments": "Emitir con prioridad"
}
```

**Respuestas:**

| HTTP | Significado |
|---|---|
| **200** | Emisión por correo ejecutada |
| **201** | Solicitud Viafirma creada (pendiente de procesamiento remoto) |
| **409** | Ya existe un trámite activo para esta solicitud |
| **422** | Validación fallida |
| **502** | Error transitorio del proveedor remoto |

### 6.2 Consultar estado del trámite

```
GET /certificate-request/{id}/issuance
```

Retorna el estado normalizado del trámite de emisión.

### 6.3 Metadata de descarga (solo Viafirma)

```
GET /certificate-request/{id}/issuance/download
```

Retorna PIN temporal + URL firmada (24h) para descargar el `.p12`.

### 6.4 Descarga binaria del P12

```
GET /certificate-request/{id}/issuance/download/file
```

Streaming directo del archivo `.p12`. Usar como `href` o `window.open()`.

---

## 7. CRUD de Solicitudes

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/certificate-request` | Listar mis solicitudes (paginado) |
| `GET` | `/certificate-request/all` | Listar todas (solo admin) |
| `GET` | `/certificate-request/{id}` | Detalle de una solicitud |
| `PUT` | `/certificate-request/{id}` | Actualizar solicitud |
| `PUT` | `/certificate-request/{id}/status` | Cambiar estado |
| `DELETE` | `/certificate-request/{id}` | Eliminar solicitud |

**Parámetros de filtro para listados:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `request_status` | string | Filtrar por estado: `DRAFT\|SENT\|PENDING\|ACCEPTED\|PROCESSING\|PROCESSED\|REJECTED` |
| `query` | string | Búsqueda por razón social, NIT, documento o representante |
| `start_date` | string | Fecha inicio (Y-m-d) |
| `end_date` | string | Fecha fin (Y-m-d) |
| `limit` | integer | Registros por página (default: 15) |

---

## Resumen de Endpoints

| # | Método | Endpoint | Propósito |
|---|---|---|---|
| 1 | `GET` | `/quota/status` | Consultar disponibilidad de cupos |
| 2 | `GET` | `/pricing` | Ver tarifas |
| 3 | `POST` | `/orders` | Crear orden de compra |
| 4 | `POST` | `/orders/{id}/pay` | Ejecutar pago WOMPI |
| 5 | `GET` | `/orders` | Listar órdenes de la empresa |
| 6 | `GET` | `/orders/{id}` | Detalle de una orden (polling estado) |
| 7 | `POST` | `/certificate-request` | Crear solicitud de certificado |
| 8 | `GET` | `/certificate-request` | Listar mis solicitudes |
| 9 | `GET` | `/certificate-request/all` | Listar todas (admin) |
| 10 | `GET` | `/certificate-request/{id}` | Detalle de una solicitud |
| 11 | `PUT` | `/certificate-request/{id}` | Actualizar solicitud |
| 12 | `PUT` | `/certificate-request/{id}/status` | Cambiar estado |
| 13 | `DELETE` | `/certificate-request/{id}` | Eliminar solicitud |
| 14 | `POST` | `/certificate-request/{id}/issue` | Disparar emisión |
| 15 | `GET` | `/certificate-request/{id}/issuance` | Estado del trámite |
| 16 | `GET` | `/certificate-request/{id}/issuance/download` | Metadata de descarga |
| 17 | `GET` | `/certificate-request/{id}/issuance/download/file` | Descarga binaria P12 |

---

## Manejo de Errores Global

Todas las respuestas de error siguen el formato estándar:

```json
{
  "success": false,
  "message": "Descripción del error"
}
```

| HTTP | Significado | Acción sugerida |
|---|---|---|
| `401` | Token inválido o expirado | Redirigir a login |
| `402` | Sin cupo de certificados | Redirigir a compra |
| `403` | Sin permisos (acción de admin) | Mostrar "Acceso denegado" |
| `404` | Recurso no encontrado | Mostrar "No encontrado" |
| `422` | Error de validación | Mostrar errores por campo |
| `429` | Rate limit alcanzado | Mostrar "Espere un momento" |
| `500` | Error interno del servidor | Mostrar "Error inesperado, intente más tarde" |
