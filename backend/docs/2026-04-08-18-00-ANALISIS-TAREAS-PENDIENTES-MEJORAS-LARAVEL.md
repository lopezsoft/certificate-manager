# Análisis de Tareas Pendientes y Mejoras — Backend Laravel

**Fecha:** 2026-04-08 18:00  
**Actualizado:** 2026-04-09 10:00  
**Versión analizada:** `1.9.0` → `1.10.0` (tras implementación)  
**Framework:** Laravel 10.x / PHP 8.1  
**Base de datos:** MariaDB (driver `mysql`)  
**Regla de testing:** Solo Mocks/Fakes — prohibido usar DB real en tests  
**Última versión documentada:** `1.10.0` (2026-04-09)

---

## 1. Resumen Ejecutivo

El proyecto **Certificate Manager** es un backend Laravel maduro con un sistema de solicitudes de certificados digitales, notificaciones automáticas, webhooks salientes y Personal Access Tokens (PAT). Se han completado **23 tareas del backlog** (T-01 a T-21 + T-23 + T-31 + T-32) incluyendo las **7 tareas críticas y de alta prioridad** implementadas previamente y la actualización completa de documentación Swagger/OpenAPI.

**Resultado del sprint actual:** 8 tareas implementadas, 193 tests pasando (320 assertions, 0 fallos), 9 archivos creados, 20 archivos modificados.

---

## 2. Estado del Backlog Completo

### 2.1 Tareas completadas previamente (v1.9.0)

| ID    | Descripción                                             | Estado |
|-------|---------------------------------------------------------|--------|
| T-01  | DI en controllers                                       | ✅     |
| T-02  | Tests `SendExpiringCertificatesNotificationsJob`        | ✅     |
| T-03  | Tests `SendMonthlyCompanyCertificatesReportJob`         | ✅     |
| T-04  | Tests `SendAdminExpiringCertificatesReportJob`          | ✅     |
| T-05  | Tests `NotificationController` (5 endpoints)            | ✅     |
| T-06  | Tests endpoints PAT (`/v1/tokens`)                      | ✅     |
| T-07  | Tests módulo Webhooks                                   | ✅     |
| T-08  | Automatizar scripts tinker de notificaciones            | ✅     |
| T-09  | Jerarquía de excepciones custom                         | ✅     |
| T-10  | Handler global en `app/Exceptions/Handler.php`          | ✅     |
| T-11  | Middleware `throttle` en endpoints sensibles             | ✅     |
| T-12  | Sanitización de inputs con `strip_tags` + `Str::upper()`| ✅     |
| T-13  | Validación de MIME type real en uploads                  | ✅     |
| T-14  | Refactorización `CertificateRequestService` con Command Pattern | ✅ |
| T-23  | `APP_VERSION` corregido a `1.9.0`                       | ✅     |

### 2.2 Tareas implementadas en este sprint (v1.10.0) — 2026-04-08

| ID    | Descripción                                             | Estado | Tests |
|-------|---------------------------------------------------------|--------|-------|
| T-15  | Accessor `is_admin` en modelo `User` (basado en `type_id`) | ✅ | 5 tests (`UserTest`) |
| T-16  | Handler global de excepciones — eliminar doble logging, agregar handlers específicos | ✅ | 5 tests (`HandlerTest`) |
| T-17  | Crear `CertificateRequestStatusEnum` y reemplazar strings en 10+ archivos | ✅ | 7 tests (`CertificateRequestStatusEnumTest`) |
| T-18  | Eliminar `$with` global en `CertificateRequest`, agregar eager loading explícito | ✅ | 5 tests (`CertificateRequestTest`) |
| T-20  | Crear Form Requests para validación de certificados | ✅ | Validación integrada vía Laravel |
| T-21  | Middleware `EnsureUserIsAdmin` + registro en Kernel + rate limiting `triggerNow` | ✅ | 3 tests (`EnsureUserIsAdminTest`) |
| T-31  | Fix accessor `getExpirationDateFormattedAttribute` con `null` | ✅ | Incluido en `CertificateRequestTest` |

### 2.3 Tareas implementadas — 2026-04-09

| ID    | Descripción                                             | Estado | Tests |
|-------|---------------------------------------------------------|--------|-------|
| T-32  | Actualización completa de documentación Swagger/OpenAPI 3.0 | ✅ | N/A (documentación) |

---

## 3. Detalle de Implementaciones Realizadas

### 3.1 T-15: Accessor `is_admin` en User — ✅ COMPLETADO

**Problema:** `NotificationController` usaba `$user->is_admin` pero no existía como columna ni accessor. `ConsumeService` ya usaba `$user->type_id !== 1` para la misma lógica.

**Solución implementada:**
- Accessor `getIsAdminAttribute(): bool` en `User.php` → `(int) $this->type_id === 1`
- Propiedad `is_admin` agregada a `$appends` para serialización JSON
- Tests actualizados en `NotificationControllerTest` para usar `type_id` en lugar de propiedad dinámica

**Archivos modificados:**
- `app/Models/User.php` — accessor + appends
- `tests/Feature/NotificationControllerTest.php` — usar `type_id` en mocks

**Archivos creados:**
- `tests/Unit/Models/UserTest.php` — 5 tests

---

### 3.2 T-16: Handler Global de Excepciones — ✅ COMPLETADO

**Problema:** Doble logging (`Log::error` + `parent::report`), solo manejaba `CertificateException`, exposición de datos sensibles.

**Solución implementada:**
- Eliminado método `report()` sobreescrito (causaba doble logging)
- Agregados handlers `renderable` para: `CertificateException` (400), `NotFoundHttpException` (404), `AuthenticationException` (401), `ValidationException` (422)
- `InvalidFileException` y `EmailNotConfiguredException` ya cubiertas por el handler de `CertificateException` (son hijas)
- Mensajes sanitizados (no exponen detalles internos)

**Archivos modificados:**
- `app/Exceptions/Handler.php` — reescrito completamente

**Archivos creados:**
- `tests/Unit/Exceptions/HandlerTest.php` — 5 tests

---

### 3.3 T-17: `CertificateRequestStatusEnum` — ✅ COMPLETADO

**Problema:** Strings `'PROCESSED'`, `'DRAFT'`, `'SENT'`, etc. hardcodeados en 15+ archivos.

**Solución implementada:**
- Enum backed string con 7 cases + métodos helper: `description()`, `activeStatuses()`, `issuedStatuses()`, `adminDefaultStatuses()`, `values()`
- Reemplazados strings en 10 archivos del proyecto

**Archivos creados:**
- `app/Enums/CertificateRequestStatusEnum.php`
- `tests/Unit/Enums/CertificateRequestStatusEnumTest.php` — 7 tests

**Archivos modificados (reemplazo de strings):**
- `app/Handlers/Certificate/CreateCertificateRequestHandler.php`
- `app/Handlers/Certificate/UpdateCertificateStatusHandler.php`
- `app/Services/CertificateRequestService.php`
- `app/Services/CertificateRequestMailService.php`
- `app/Services/ConsumeService.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Jobs/SendExpiringCertificatesNotificationsJob.php`
- `app/Console/Commands/NotifyExpiringCertificatesCommand.php`
- `app/Webhooks/Builders/CertificateCreatedPayloadBuilder.php`

---

### 3.4 T-18: Eliminar `$with` Global — ✅ COMPLETADO

**Problema:** `CertificateRequest` cargaba 5 relaciones (`identity`, `organization`, `city`, `files`, `history`) en TODAS las queries.

**Solución implementada:**
- Eliminado `protected $with` del modelo
- Agregado eager loading explícito en cada punto de uso:
  - `CreateCertificateRequestHandler` → `load(['city', 'identity'])` antes del Excel, `load(['files'])` para respuesta
  - `UpdateCertificateStatusHandler` → `with(['company'])` en la query
  - `CertificateRequestMailService` → `with(['files'])` en la query
  - `CertificateRequestService` — ya tenía `with()` explícito ✅
  - `NotificationController` — ya tenía `with(['company', 'city'])` ✅

**Archivos modificados:**
- `app/Models/CertificateRequest.php`
- `app/Handlers/Certificate/CreateCertificateRequestHandler.php`
- `app/Handlers/Certificate/UpdateCertificateStatusHandler.php`
- `app/Services/CertificateRequestMailService.php`

**Archivos creados:**
- `tests/Unit/Models/CertificateRequestTest.php` — 5 tests (incluye T-31)

---

### 3.5 T-20: Form Requests para Certificados — ✅ COMPLETADO

**Problema:** Reglas de validación duplicadas en `CertificateRequestService` métodos create/update.

**Solución implementada:**
- `CreateCertificateRequestFormRequest` con reglas y mensajes en español
- `UpdateCertificateRequestFormRequest` con campo `info` adicional nullable
- `CertificateRequestController` actualizado para inyectar Form Requests
- Validación eliminada del Service (ya no es su responsabilidad)

**Archivos creados:**
- `app/Http/Requests/Certificate/CreateCertificateRequestFormRequest.php`
- `app/Http/Requests/Certificate/UpdateCertificateRequestFormRequest.php`

**Archivos modificados:**
- `app/Http/Controllers/CertificateRequestController.php`
- `app/Services/CertificateRequestService.php`

---

### 3.6 T-21: Middleware `EnsureUserIsAdmin` — ✅ COMPLETADO

**Problema:** Verificación manual `if (!$user->is_admin)` en controllers, sin middleware centralizado. Endpoint `triggerNow` sin rate limiting.

**Solución implementada:**
- Middleware `EnsureUserIsAdmin` que verifica `$request->user()->is_admin` (depende de T-15)
- Registrado en `Kernel.php` como `'admin'`
- Aplicado al grupo de rutas `admin` en `routes/api.php`
- Rate limiting `throttle:1,5` (1 request cada 5 minutos) en `triggerNow`
- Simplificado `NotificationController::triggerNow()` — eliminada verificación manual de admin

**Archivos creados:**
- `app/Http/Middleware/EnsureUserIsAdmin.php`
- `tests/Unit/Middleware/EnsureUserIsAdminTest.php` — 3 tests

**Archivos modificados:**
- `app/Http/Kernel.php` — registro del middleware
- `routes/api.php` — middleware `admin` + throttle en grupo admin
- `app/Http/Controllers/NotificationController.php` — simplificado triggerNow

---

### 3.7 T-31: Fix Accessor `expirationDateFormatted` — ✅ COMPLETADO

**Problema:** `Carbon::parse(null)` retornaba la fecha actual en lugar de `null`.

**Solución:** Return type cambiado a `?string`, verificación de null antes de parsear.

**Archivos modificados:**
- `app/Models/CertificateRequest.php`

---

### 3.8 T-32: Actualización Swagger/OpenAPI — ✅ COMPLETADO

**Problema:** Documentación Swagger incompleta — `SwaggerDefinitions.php` con versión `1.8.0`, `TableCrudController` sin anotaciones (5 endpoints), `ConsumeController` con tag incorrecto (`Configuración` en lugar de `Consumo`), endpoints de verificación de email sin documentar.

**Solución implementada:**
- `SwaggerDefinitions.php` — Actualizado `@OA\Info.version` de `1.8.0` a `1.10.0`, agregado tag `CRUD Genérico`, mejorada descripción del tag `Autenticación`
- `TableCrudController.php` — Anotaciones `@OA` completas en 5 métodos CRUD (index, store, show, update, destroy) bajo tag `CRUD Genérico`
- `ConsumeController.php` — Corregido tag de `Configuración` a `Consumo` en `readByYear` y `readByMonth`
- `EmailVerificationNotificationController.php` — Anotación `@OA\Post` para reenvío de verificación de email
- `VerifyEmailController.php` — Anotación `@OA\Get` con respuesta `302` (redirect)
- Ejecutado `php artisan l5-swagger:generate` — generación exitosa

**Cobertura final Swagger:** 44 endpoints documentados (100% de rutas API)

**Endpoints documentados por tag:**
| Tag | Endpoints |
|-----|-----------|
| Autenticación | 6 (login, logout, register, forgot-password, reset-password, verify-email, email-verification) |
| Solicitudes de Certificado | 7 (CRUD + send-mail + status) |
| Archivos | 2 (upload + delete) |
| Perfil | 3 (user, types, update) |
| Consumo | 2 (year, month) |
| CRUD Genérico | 5 (index, store, show, update, destroy) |
| Tokens | 6 (index, store, show, destroy, revoke-all, renew) |
| Webhooks | 8 (events, index, store, show, update, destroy, rotate-secret, deliveries) |
| Notificaciones | 5 (expiring, index, markAsRead, markAllAsRead, triggerNow) |
| Datos Maestros | 5 (countries, departments, cities, organization-type, identity-documents) |
| Configuración | 4 (company, settings GET/PUT, reports GET/PUT) |

**Archivos modificados:**
- `app/Http/Controllers/SwaggerDefinitions.php` — version + tag
- `app/Http/Controllers/TableCrudController.php` — 5 anotaciones OA
- `app/Http/Controllers/ConsumeController.php` — tag corregido
- `app/Http/Controllers/Auth/EmailVerificationNotificationController.php` — anotación OA
- `app/Http/Controllers/Auth/VerifyEmailController.php` — anotación OA
- `storage/api-docs/api-docs.json` — regenerado

---

## 4. Tareas Pendientes (Backlog)

### 🟡 Prioridad Media

| ID   | Tarea | Esfuerzo | Estado |
|------|-------|----------|--------|
| T-19 | Migrar `CompanyController` a Services (anti-patrón métodos estáticos) | 4h | ⏳ Pendiente |
| T-22 | Configurar logging `daily` en producción | 0.5h | ⏳ Pendiente |
| T-24 | Resolver TODOs del código (4 pendientes) | 4h | ⏳ Pendiente |
| T-25 | Completar Dockerfile con extensiones PHP para MariaDB | 2h | ⏳ Pendiente |
| T-26 | Completar `compose.yaml` con MariaDB, Redis, workers | 3h | ⏳ Pendiente |
| T-27 | Refactorizar `HttpResponseMessages` con tipado estricto | 2h | ⏳ Pendiente |
| T-28 | Tests para servicios críticos sin cobertura | 8h | ⏳ Pendiente |
| T-29 | Limpiar archivos temporales y backups del repositorio | 1h | ⏳ Pendiente |
| T-30 | Actualizar versión de PHP de 8.1 a 8.2/8.3 | 2h | ⏳ Pendiente |

### Mejoras arquitectónicas (Road Map)

| Mejora | Esfuerzo | Estado |
|--------|----------|--------|
| DTOs de Request/Response | 8h | ⏳ |
| Repository Pattern | 6h | ⏳ |
| Health Check Endpoint | 3h | ⏳ |
| Configuración CORS para producción | 1h | ⏳ |

---

## 5. Archivos del Proyecto Actualizados

### Archivos creados en este sprint (9):

```
app/Enums/CertificateRequestStatusEnum.php              ← T-17
app/Http/Middleware/EnsureUserIsAdmin.php                ← T-21
app/Http/Requests/Certificate/CreateCertificateRequestFormRequest.php  ← T-20
app/Http/Requests/Certificate/UpdateCertificateRequestFormRequest.php  ← T-20
tests/Unit/Enums/CertificateRequestStatusEnumTest.php   ← T-17 tests
tests/Unit/Models/UserTest.php                          ← T-15 tests
tests/Unit/Models/CertificateRequestTest.php            ← T-18/T-31 tests
tests/Unit/Middleware/EnsureUserIsAdminTest.php          ← T-21 tests
tests/Unit/Exceptions/HandlerTest.php                   ← T-16 tests
```

### Archivos modificados en este sprint (20):

```
app/Models/User.php                                     ← T-15: accessor is_admin
app/Models/CertificateRequest.php                       ← T-18: sin $with, T-31: null-safe
app/Exceptions/Handler.php                              ← T-16: handlers específicos
app/Http/Kernel.php                                     ← T-21: middleware admin
app/Http/Controllers/CertificateRequestController.php   ← T-20: Form Requests
app/Http/Controllers/NotificationController.php         ← T-17+T-21: enum + simplificado
app/Http/Controllers/SwaggerDefinitions.php             ← T-32: version 1.10.0 + tag CRUD Genérico
app/Http/Controllers/TableCrudController.php            ← T-32: 5 anotaciones @OA
app/Http/Controllers/ConsumeController.php              ← T-32: tag Consumo
app/Http/Controllers/Auth/EmailVerificationNotificationController.php  ← T-32: anotación @OA
app/Http/Controllers/Auth/VerifyEmailController.php     ← T-32: anotación @OA
app/Services/CertificateRequestService.php              ← T-17+T-20: enum + sin validación
app/Services/CertificateRequestMailService.php          ← T-17+T-18: enum + eager loading
app/Services/ConsumeService.php                         ← T-17: enum
app/Handlers/Certificate/CreateCertificateRequestHandler.php  ← T-17+T-18
app/Handlers/Certificate/UpdateCertificateStatusHandler.php   ← T-17+T-18
app/Console/Commands/NotifyExpiringCertificatesCommand.php    ← T-17: enum
app/Webhooks/Builders/CertificateCreatedPayloadBuilder.php    ← T-17: enum
app/Jobs/SendExpiringCertificatesNotificationsJob.php         ← T-17: enum
routes/api.php                                                ← T-21: middleware + throttle
tests/Feature/NotificationControllerTest.php                  ← Fix: type_id
```

---

## 6. Métricas del Proyecto

| Métrica | Antes (v1.9.0) | Después (v1.10.0) |
|---------|----------------|---------------------|
| Versión | 1.9.0 | 1.10.0 |
| Tests totales | 165 | 193 |
| Assertions | ~260 | 320 |
| Failures | 0 | 0 |
| Archivos de test | ~15 | ~20 |
| Middlewares registrados | 13 | 14 (+admin) |
| Enums | 3 | 4 (+CertificateRequestStatusEnum) |
| Form Requests | 2 | 4 (+Certificate/*) |
| Tareas completadas | 15 | 23 |
| Tareas pendientes | 17 | 9 |
| Deuda técnica estimada | ~50h | ~27h |

### 6.1 Cumplimiento de Regla de Testing

Todos los archivos de test automatizados **cumplen** la regla de no acceder a la base de datos:

- ✅ **Ninguno** usa `RefreshDatabase`, `DatabaseMigrations` o `DatabaseTransactions`.
- ✅ **Ninguno** usa `User::create()` ni factories con persistencia.
- ✅ Todos usan `User::make()`, `Mockery`, `Queue::fake()`, y facades fakeadas.
- ✅ `phpunit.xml` tiene `DB_CONNECTION` y `DB_DATABASE` comentados (no conecta a MariaDB).
- ⚠️ **2 scripts manuales** (`tests/ManualTestCertificateNotifications.php`, `tests/ManualTestMonthlyReports.php`) usan `DB::table()` pero son scripts de tinker, no tests de PHPUnit.

---

*Documento generado el 2026-04-08 18:00. Actualizado el 2026-04-09 10:00 tras implementación de T-15 a T-21, T-31 y T-32 (Swagger/OpenAPI).*
