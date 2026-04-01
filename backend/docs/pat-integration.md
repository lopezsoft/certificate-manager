# Integración de Personal Access Tokens (PAT)

**Fecha:** 19 de Febrero, 2026
**Versión:** 1.7.0
**Audiencia:** Agente SPA / Desarrolladores frontend

---

## Resumen

El sistema soporta **Personal Access Tokens (PAT)** para autenticación de larga duración, orientados a integraciones externas (ERPs, scripts, CLIs). El valor del token solo se expone en el momento de la creación o renovación — nunca después.

**Base URL:** `/api/v1/tokens`
**Autenticación requerida:** Bearer token (sesión de Passport OAuth)

---

## Endpoints

### GET /tokens — Listar tokens activos

Retorna todos los PAT activos (no revocados, no expirados) del usuario autenticado.

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "dataRecords": {
    "data": [
      {
        "id": "9d471f3b-8c6e-4a2d-b9f0-1234567890ab",
        "name": "Token ERP Producción",
        "scopes": ["*"],
        "created_at": "2026-02-19 10:00:00",
        "expires_at": "2026-05-19 10:00:00"
      }
    ]
  }
}
```

---

### POST /tokens — Crear token

Genera un nuevo PAT. **El campo `token` solo aparece en esta respuesta.**

**Rate limit:** 10 tokens por día por usuario.

**Body (JSON):**
```json
{
  "name": "Token ERP Producción",
  "expires_in_days": 90,
  "abilities": ["*"]
}
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `name` | string (3–255) | ✅ | Nombre descriptivo del token |
| `expires_in_days` | integer (1–365) | ❌ | Días de validez. Default: 90 |
| `abilities` | array de strings | ❌ | Permisos. Default: `["*"]` (acceso completo) |

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "id": "9d471f3b-8c6e-4a2d-b9f0-1234567890ab",
    "name": "Token ERP Producción",
    "token": "1|aB3cD4eF5gH6iJ7kL8mN9oP0qR1sT2uV3wX4yZ5",
    "expires_at": "2026-05-19 10:00:00",
    "created_at": "2026-02-19 10:00:00"
  },
  "message": "Token creado. Guárdelo ahora, no se mostrará de nuevo."
}
```

**Errores:**
```json
// 422 — Validación
{
  "success": false,
  "message": "El nombre del token es requerido."
}

// 429 — Límite diario alcanzado
{
  "success": false,
  "message": "Límite de creación de tokens alcanzado. Máximo 10 por día."
}
```

---

### GET /tokens/{id} — Detalle de un token

Retorna los metadatos del token. **No incluye el valor del token.**

**Parámetro:** `id` — UUID del token (obtenido al crear o listar).

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "id": "9d471f3b-8c6e-4a2d-b9f0-1234567890ab",
    "name": "Token ERP Producción",
    "scopes": ["*"],
    "revoked": false,
    "expires_at": "2026-05-19 10:00:00",
    "created_at": "2026-02-19 10:00:00"
  }
}
```

**Error:**
```json
// 404 — No encontrado o no pertenece al usuario
{ "success": false, "message": "No query results for model [Token]." }
```

---

### DELETE /tokens/{id} — Revocar token específico

Invalida inmediatamente el token. No se puede deshacer.

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "message": "Token revocado exitosamente."
}
```

---

### POST /tokens/revoke-all — Revocar todos los tokens

Invalida todos los PAT activos del usuario. Útil ante compromiso de credenciales.

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "message": "3 token(s) revocado(s) exitosamente."
}
```

---

### POST /tokens/{id}/renew — Renovar token

Crea un nuevo token con el mismo nombre + `(renovado)` y los mismos permisos. Revoca el anterior automáticamente. **El campo `token` solo aparece en esta respuesta.**

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "name": "Token ERP Producción (renovado)",
    "token": "2|xY9zA0bC1dE2fG3hI4jK5lM6nO7pQ8rS9tU0v",
    "expires_at": "2026-08-19 10:00:00",
    "created_at": "2026-05-19 10:00:00"
  },
  "message": "Token renovado. Guárdelo ahora, no se mostrará de nuevo."
}
```

---

## Configuración (variables de entorno)

| Variable | Defecto | Descripción |
|----------|---------|-------------|
| `PAT_EXPIRATION_DAYS` | `90` | Días de validez por defecto de un PAT nuevo |
| `PAT_MAX_EXPIRATION_DAYS` | `365` | Máximo de días que puede solicitar el cliente |
| `PAT_MAX_PER_DAY` | `10` | Máximo de tokens creables por usuario por día |
| `PAT_MAX_ACTIVE` | `20` | Máximo de tokens activos simultáneos por usuario |

---

## Flujo de uso recomendado para el SPA

### Pantalla de gestión de tokens

1. **Al cargar:** `GET /tokens` → mostrar lista con nombre, estado activo y fecha de expiración.
2. **Crear nuevo:** Formulario con `name` y `expires_in_days` (slider 30–365) → `POST /tokens` → **mostrar el `token` en un modal con botón "Copiar"** (solo aparece una vez).
3. **Revocar:** Confirmación → `DELETE /tokens/{id}` → refrescar lista.
4. **Revocar todo:** Confirmación con advertencia → `POST /tokens/revoke-all`.
5. **Renovar:** Botón "Renovar" en tokens próximos a vencer → `POST /tokens/{id}/renew` → **mostrar nuevo `token` en modal**.

### Indicadores de estado recomendados

| Condición | Indicador visual |
|-----------|-----------------|
| `expires_at` > 30 días | 🟢 Verde — Activo |
| `expires_at` <= 30 días | 🟡 Amarillo — Próximo a vencer |
| `expires_at` <= 7 días | 🔴 Rojo — Urgente renovar |
| `revoked === true` | ⛔ Gris — Revocado |

---

## Reglas de negocio

- El valor del token (`token`) **solo se expone una vez**: al crear (`POST /tokens`) o renovar (`POST /tokens/{id}/renew`). Tras eso no es recuperable.
- Los tokens son **scoped al usuario**: un usuario solo puede ver/gestionar sus propios tokens.
- Los tokens son **multi-empresa seguros**: el `company_id` se infiere del usuario autenticado.
- **Rate limit de creación:** 10 tokens por día por usuario (HTTP 429 si se excede).
- **Expiración global:** 90 días por defecto. Configurable hasta 365 días.
- El endpoint `POST /tokens/revoke-all` **también revoca el token de sesión actual** si fue creado con `createToken()`. Diseñar el UX para advertir esto.

---

## Uso del PAT como autenticación de API

Una vez creado, el PAT se usa como Bearer token en todas las peticiones:

```
Authorization: Bearer 1|aB3cD4eF5gH6iJ7kL8mN9oP0qR1sT2uV3wX4yZ5
```

Es idéntico al token de sesión en términos de uso HTTP. La diferencia es la duración (90 días vs sesión corta).
