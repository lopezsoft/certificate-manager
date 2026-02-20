# Changelog

Todos los cambios notables de este proyecto están documentados en este archivo.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es/1.0.0/).
El versionado sigue [Semantic Versioning](https://semver.org/lang/es/).

---

## [1.9.0] - 2026-02-20

### Añadido
- **Command Pattern en `CertificateRequestService`** (T-14):
  - `app/Commands/Certificate/` — interfaz marcadora + 4 DTOs `readonly` (`CreateCertificateRequestCommand`, `UpdateCertificateRequestCommand`, `UpdateCertificateStatusCommand`, `DeleteCertificateRequestCommand`)
  - `app/Handlers/Certificate/` — 4 handlers con responsabilidad única (`CreateCertificateRequestHandler`, `UpdateCertificateRequestHandler`, `UpdateCertificateStatusHandler`, `DeleteCertificateRequestHandler`)
  - `CertificateRequestService` reescrito como fachada delgada: valida → construye Command → delega al handler
  - `AppServiceProvider` actualizado con 4 singletons de handlers
- **Suite de tests automatizados — 165 tests / 0 fallos** (regla: solo mocks, sin DB):
  - `tests/Unit/Commands/Certificate/CertificateCommandTest` — 18 tests (DTOs readonly, interfaz, tipos)
  - `tests/Unit/Handlers/Certificate/CertificateHandlerStructureTest` — 17 tests (Reflection, firmas, namespaces)
  - `tests/Unit/Handlers/Certificate/UpdateCertificateStatusHandlerNotificationTest` — 8 tests (Mockery, lógica de notificaciones)
  - `tests/Unit/Jobs/SendMonthlyCompanyCertificatesReportJobTest` — 11 tests (T-03)
  - `tests/Unit/Jobs/SendAdminExpiringCertificatesReportJobTest` — 8 tests (T-04)
  - `tests/Feature/AutomatedManualNotificationsTest` — 11 tests, convierte scripts tinker en tests automatizados (T-08)
- **Migraciones de webhooks** ejecutadas: `webhook_endpoints` y `webhook_deliveries`

### Cambiado
- Eliminados 6 tests boilerplate de Laravel Breeze (`tests/Feature/Auth/*`, `tests/Feature/ProfileTest`) que usaban `RefreshDatabase` y ejecutaban SQL contra la DB
- Import muerto `RefreshDatabase` limpiado de `tests/Feature/ExampleTest`

### Completado (Backlog)
| Tarea | Descripción |
|-------|-------------|
| T-01 | DI en controllers |
| T-02 | Tests `SendExpiringCertificatesNotificationsJob` |
| T-03 | Tests `SendMonthlyCompanyCertificatesReportJob` |
| T-04 | Tests `SendAdminExpiringCertificatesReportJob` |
| T-05 | Tests `NotificationController` (5 endpoints) |
| T-06 | Tests endpoints PAT (`/v1/tokens`) |
| T-07 | Tests módulo Webhooks |
| T-08 | Automatizar scripts tinker de notificaciones |
| T-09 | Jerarquía de excepciones custom |
| T-10 | Handler global en `app/Exceptions/Handler.php` |
| T-11 | Middleware `throttle` en endpoints sensibles |
| T-12 | Sanitización de inputs con `strip_tags` + `Str::upper()` |
| T-13 | Validación de MIME type real en uploads |
| T-14 | Refactorización `CertificateRequestService` con Command Pattern |
| T-23 | `APP_VERSION` corregido a `1.9.0` |

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
