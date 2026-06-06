# Guía Frontend — Cambios Backend Viafirma PJ

## 1. Login: campo `issuance_provider`

El login retorna el proveedor de emisión de la empresa:

```json
{
  "access_token": "...",
  "user": { ... },
  "company": {
    "has_agreement": true,
    "company_type_id": 1,
    "issuance_provider": "viafirma",
    "company_name": "MI EMPRESA S.A.S.",
    "uuid": "..."
  }
}
```

| Valor | Significado |
|---|---|
| `"mail"` o `null` | Flujo legacy — requiere archivos adjuntos |
| `"viafirma"` | Flujo Viafirma — sin archivos, campos adicionales |

**Acción**: Guardar `company.issuance_provider` en el estado/sesión. Si es `null`, tratar como `"mail"`.

---

## 2. Nuevo endpoint de catálogo

```
GET /api/v1/entity-document-types
```

**Respuesta**:
```json
{
  "success": true,
  "dataRecords": {
    "data": [
      { "id": 1, "code": "CC", "description": "Cámara de Comercio", "active": true },
      { "id": 2, "code": "PJ", "description": "Personería Jurídica", "active": true },
      { "id": 3, "code": "AC", "description": "Acta o certificado de constitución", "active": true },
      { "id": 4, "code": "DN", "description": "Decreto de nombramiento", "active": true },
      { "id": 99, "code": "OT", "description": "Otro documento de Constitución", "active": true }
    ]
  }
}
```

**Acción**: Cargar este catálogo junto con `identity-documents` y `organization-type` al iniciar el formulario.

---

## 3. Formulario: campos nuevos en `POST /api/v1/certificate-request`

| Campo | Tipo | Requerido | Default |
|---|---|---|---|
| `entity_document_type_id` | `int` | No (default 1) | `1` (Cámara de Comercio) |
| `legal_rep_email` | `string \| null` | Solo cuando viafirma + PJ | `null` |

### Archivos adjuntos

| `issuance_provider` | Archivos | Comportamiento |
|---|---|---|
| `"mail"` o `null` | **Obligatorios** (mín 2, máx 3) | Flujo actual sin cambios |
| `"viafirma"` | **No se envían** | Ocultar sección de upload |

---

## 4. Lógica condicional del formulario

```
SI issuance_provider === "viafirma"
│
├── Ocultar sección de archivos adjuntos
│
├── SI type_organization_id === 1 (Persona Jurídica)
│     ├── Mostrar select "Tipo de documento constitutivo" → entity_document_type_id
│     ├── Mostrar input "Email del representante legal"   → legal_rep_email [requerido]
│     │
│     ├── SI entity_document_type_id === 1 (Cámara de Comercio)
│     │     └── Info: "Se enviará enlace de verificación biométrica"
│     │
│     └── SI entity_document_type_id !== 1 (Personería Jurídica, Acta, Decreto, Otro)
│           └── Info: "Viafirma contactará al representante legal por email"
│
└── SI type_organization_id === 2 (Persona Natural)
      └── No mostrar campos adicionales


SI issuance_provider === "mail" o null
├── Mostrar sección de archivos adjuntos (flujo legacy)
└── No mostrar campos de documento constitutivo ni email rep. legal
```

---

## 5. Ejemplos de payload

### Viafirma + PJ + Cámara de Comercio

```json
{
  "city_id": 149,
  "identity_document_id": 1,
  "type_organization_id": 1,
  "entity_document_type_id": 1,
  "document_number": "1234567890",
  "address": "Calle 123 # 45-67",
  "legal_representative": "JUAN PÉREZ",
  "legal_rep_email": "juan.perez@empresa.com",
  "company_name": "MI EMPRESA S.A.S.",
  "dni": "900455420",
  "life": 1
}
```

> Sin archivos. Enviar como `application/json`.

### Viafirma + PJ + Personería Jurídica

```json
{
  "city_id": 149,
  "identity_document_id": 1,
  "type_organization_id": 1,
  "entity_document_type_id": 2,
  "document_number": "1234567890",
  "address": "Calle 123 # 45-67",
  "legal_representative": "JUAN PÉREZ",
  "legal_rep_email": "juan.perez@empresa.com",
  "company_name": "MI EMPRESA S.A.S.",
  "dni": "900455420",
  "life": 1
}
```

> Sin archivos. Viafirma contactará por email.

### Mail (legacy)

```
Content-Type: multipart/form-data

city_id: 149
identity_document_id: 1
type_organization_id: 1
document_number: 1234567890
address: Calle 123 # 45-67
legal_representative: JUAN PÉREZ
company_name: MI EMPRESA S.A.S.
dni: 900455420
life: 1
files[0]: (archivo.pdf)
files[1]: (archivo2.pdf)
```

> `entity_document_type_id` se omite → backend asigna `1` por defecto.

---

## 6. Resumen de cambios en Angular

| Servicio/Componente | Cambio |
|---|---|
| **AuthService / Login** | Guardar `company.issuance_provider` en sesión |
| **CatalogService** | Agregar llamada a `GET /entity-document-types` |
| **CertificateRequestForm** | Campos condicionales según proveedor y tipo org. |
| **CertificateRequestService** | JSON cuando viafirma, FormData cuando mail |
| **Validaciones** | `legal_rep_email` requerido cuando viafirma + PJ |

---

## 7. Endpoint eliminado

`POST /certificate-request/{id}/send-mail` → **Ya no existe**.

Todo pasa por `POST /certificate-request/{id}/issue` que despacha al proveedor correcto automáticamente.
