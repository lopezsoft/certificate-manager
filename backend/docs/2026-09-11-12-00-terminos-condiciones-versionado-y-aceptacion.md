# Términos y Condiciones: versionado y evidencia de aceptación

**Fecha:** 2026-09-11
**Fuente oficial del documento:** https://maticerts.com/terminos/
**Alcance:** backend Laravel (producción). NestJS (`backnest`) no participa en esta iteración.

## Objetivo

Sustentar la cláusula 5 de los T&C (ejecución inmediata del servicio tras el pago y límite al retracto) con evidencia por solicitud: **qué versión exacta del texto aceptó el usuario, cuándo y desde qué IP**.

## Diseño

| Tabla | Rol |
|---|---|
| `terms_versions` | Una fila por publicación. Guarda `version`, `published_at`, `source_url`, `content_hash` (SHA-256) y `content` (snapshot íntegro). Solo una fila con `is_current = 1`. |
| `terms_acceptances` | Append-only. Una fila por solicitud y `consent_scope`. Guarda `terms_version_id`, `user_id`, `company_id`, `accepted_at` (UTC, servidor), `ip_address` (servidor), `user_agent`. Polimórfica (`acceptable_type/id`) para extender a órdenes sin cambiar esquema. |

El snapshot vive en BD y no en el repo: el sitio es la fuente oficial y puede cambiar, pero el texto que cada usuario aceptó queda fijado por hash.

## Despliegue

1. Ejecutar manualmente el DDL: `database/scripts/2026/09/DDL_create_terms_versions_and_acceptances.sql`.
2. Publicar la versión inicial:

```bash
php artisan terms:publish --tag=1.0 --dry-run   # revisar hash y longitud
php artisan terms:publish --tag=1.0
```

3. Cada vez que legal cambie el texto en el sitio, publicar una nueva etiqueta (`--tag=1.1`). El comando rechaza etiquetas repetidas y no publica si el texto es idéntico al vigente.

## Contrato para el front

### `GET /api/v1/terms/current` (público)

```json
{
  "success": true,
  "message": "Versión vigente de los Términos y Condiciones",
  "dataRecords": {
    "id": 1,
    "version": "1.0",
    "published_at": "2026-09-11T17:00:00.000000Z",
    "source_url": "https://maticerts.com/terminos/",
    "content_hash": "e8f4f6d8…"
  }
}
```

Responde `404` si no hay versión publicada. El front debe bloquear el envío del formulario en ese caso.

### `POST /api/v1/certificate-request` (campos nuevos, obligatorios para todos los proveedores)

| Campo | Tipo | Regla |
|---|---|---|
| `accept_terms` | boolean | `true` obligatorio |
| `terms_version_id` | integer | `id` devuelto por `GET /terms/current`. Debe ser la versión vigente. |

Errores `400` posibles en `errors`:

- `accept_terms`: "Debe aceptar los Términos y Condiciones para continuar".
- `terms_version_id`: "Los Términos y Condiciones fueron actualizados. Recargue la página y acepte la versión vigente."

La IP y el User-Agent **no se envían desde el front**; se capturan en servidor.

### Texto sugerido para el checkbox

> He leído y acepto los [Términos y Condiciones de MATICERTS](https://maticerts.com/terminos/) (v{{version}}) y autorizo que la validación de identidad y la emisión del certificado inicien de inmediato, aceptando que, una vez emitido, el servicio no admite retracto ni reembolso (cláusula 5).

Cuando el proveedor sea Viafirma se conserva el segundo enlace a la Política de Servicios de Certificación de Viafirma, que es un requisito distinto.

### Evidencia en respuestas existentes

`GET /certificate-request/{id}` y la vista admin incluyen ahora `terms_acceptances[]` con `accepted_at`, `ip_address`, `consent_scope` y `terms_version { id, version, published_at, source_url, content_hash }`.

## Nota para legal

El texto publicado hoy en el sitio **no tiene numerales 5.3 ni 5.4**; la cláusula 5 está redactada en párrafos sin numerar y el retracto se permite "siempre que el certificado no haya sido aún emitido". El texto del checkbox se redactó en coherencia con lo publicado. Si legal desea la renuncia expresa al retracto desde el inicio del proceso, debe actualizar primero el documento en el sitio y publicar la versión 1.1.
