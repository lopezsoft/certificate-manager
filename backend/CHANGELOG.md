# Changelog

Todos los cambios notables de este proyecto están documentados en este archivo.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es/1.0.0/).
El versionado sigue [Semantic Versioning](https://semver.org/lang/es/).

---

## [1.8.0] - 2026-02-19

### Añadido
- **Sistema de notificaciones de vencimientos** — corrección y completado del sistema existente:
  - `NotificationController` con 5 endpoints para el SPA:
    - `GET /v1/certificates/expiring` — lista certificados PROCESSED próximos a vencer
    - `GET /v1/notifications` — notificaciones persistidas del usuario autenticado
    - `POST /v1/notifications/{id}/read` — marcar notificación individual como leída
    - `POST /v1/notifications/read-all` — marcar todas las notificaciones como leídas
    - `POST /v1/admin/certificates/notify-now` — disparar notificaciones manualmente (solo admin)
  - Canal `database` activado en `CertificateExpiringNotification` (persiste en tabla `notifications`)
  - Comandos Artisan para testing y triggers manuales:
    - `php artisan certificates:notify-expiring [--dry-run] [--days=30]`
    - `php artisan certificates:admin-report [--weekly]`
    - `php artisan certificates:monthly-report [--admin-only] [--company-id=]`
- **Swagger**: tag `Notificaciones`, schemas `ExpiringCertificate` y `NotificationItem`, versión `1.8.0`

### Corregido
- **Bug scheduler mensual**: `monthlyOn(Carbon::now()->endOfMonth()->day)` evaluaba el día en el arranque del proceso (día fijo). Reemplazado por `lastDayOfMonth()` para ejecución dinámica correcta cada mes.
- **Filtro de solicitudes en Job**: `SendExpiringCertificatesNotificationsJob` ahora filtra `request_status = PROCESSED`, evitando notificar certificados de solicitudes canceladas, rechazadas o en proceso.

---

## [1.7.0] - 2026-01-27

### Añadido
- Sistema de Personal Access Tokens (PAT) para integraciones externas
- Endpoints CRUD para tokens: crear, listar, revocar, renovar
- Documentación Swagger de todos los endpoints de tokens
- Guía de uso de API tokens para desarrolladores (`docs/`)
- Documentación sobre refresh tokens en sistemas PAT (`docs/`)

---

## [1.6.0] - 2026-01-20

### Añadido
- Sistema de webhooks salientes para eventos de certificados
- Endpoints para gestionar webhook endpoints: crear, listar, actualizar, eliminar
- Rotación de secretos de webhook
- Reintentos automáticos de entregas fallidas (`WebhookRetryCommand`)
- Limpieza de deliveries antiguos (`WebhookCleanupCommand`)
- Documentación Swagger de webhooks y guía de integración frontend

---

## [Anteriores]

Versiones anteriores a 1.6.0 no documentadas en este archivo.
