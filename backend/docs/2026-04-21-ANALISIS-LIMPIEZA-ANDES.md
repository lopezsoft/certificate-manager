# Análisis de Limpieza — Remoción Integración ANDES SCD
**Fecha:** 2026-04-21  
**Motivo:** Decisión administrativa de no continuar con la integración ANDES SCD.  
**Estado:** ✅ COMPLETADO — Limpieza ejecutada, tests pasando, commit realizado.

---

## 1. Resumen Ejecutivo

La integración ANDES abarca dos subsistemas:
- **ANDES ID API REST** — Verificación de identidad digital (OTP / preguntas de seguridad)
- **ANDES PKI SOAP** — Emisión de certificados digitales de firma electrónica

Estos dos subsistemas son **completamente independientes** de:
- **WOMPI** — Pasarela de pagos (se mantiene ✅)
- **Cuotas/Órdenes** (`app/Quotas/`) — Gestión de cupos prepago/postpago (se mantiene ✅)
- **Pagos** (`app/Payments/`) — Transacciones WOMPI (se mantiene ✅)

---

## 2. Inventario Completo de Impacto

### 🔴 ELIMINAR — Archivos exclusivos de ANDES (sin dependencias externas)

#### 2.1 Módulo `app/Andes/` — **ELIMINAR TODO EL DIRECTORIO** (28 archivos)

| Ruta | Descripción |
|------|-------------|
| `app/Andes/Contracts/AndesIdentityServiceContract.php` | Contrato identidad |
| `app/Andes/Contracts/AndesPkiServiceContract.php` | Contrato PKI SOAP |
| `app/Andes/DTOs/CertificateEmissionRequest.php` | DTO emisión certificado |
| `app/Andes/DTOs/CertificateEmissionResponse.php` | DTO respuesta emisión |
| `app/Andes/DTOs/CertificateQueryResponse.php` | DTO consulta certificado |
| `app/Andes/DTOs/IdentityValidationRequest.php` | DTO validación identidad |
| `app/Andes/DTOs/IdentityValidationResponse.php` | DTO respuesta validación |
| `app/Andes/Enums/AndesCertTypeEnum.php` | Enum tipos de certificado |
| `app/Andes/Enums/AndesDocTypeEnum.php` | Enum tipos de documento |
| `app/Andes/Enums/AndesFormatEnum.php` | Enum formatos de certificado |
| `app/Andes/Enums/AndesTokenStatusEnum.php` | Enum estados de token |
| `app/Andes/Enums/AndesValidationTypeEnum.php` | Enum tipos de validación |
| `app/Andes/Enums/AndesVigenciaEnum.php` | Enum vigencia |
| `app/Andes/Events/AndesCertificateEmitted.php` | Evento certificado emitido |
| `app/Andes/Events/AndesIdentityValidated.php` | Evento identidad validada |
| `app/Andes/Exceptions/AndesAuthenticationException.php` | Excepción auth |
| `app/Andes/Exceptions/AndesCertificateEmissionException.php` | Excepción emisión |
| `app/Andes/Exceptions/AndesIdentityValidationException.php` | Excepción validación |
| `app/Andes/Http/Middleware/AndesRateLimiterMiddleware.php` | Rate limiter ANDES |
| `app/Andes/Jobs/PollAndesCertificateStatusJob.php` | Job polling estado |
| `app/Andes/Models/AndesCertificateRequest.php` | Modelo solicitud ANDES |
| `app/Andes/Models/AndesIdentityValidation.php` | Modelo validación identidad |
| `app/Andes/Services/AndesDataMapper.php` | Mapper datos → DTO SOAP |
| `app/Andes/Services/AndesHealthCheckService.php` | Health check ANDES |
| `app/Andes/Services/AndesIdentityService.php` | Servicio identidad |
| `app/Andes/Services/AndesPkiService.php` | Servicio PKI SOAP |
| `app/Andes/Services/AndesSoapClientFactory.php` | Factory cliente SOAP |
| `app/Andes/Services/AndesTokenManager.php` | Gestor tokens OAuth |

#### 2.2 Controllers V2 exclusivos de ANDES

| Ruta | Descripción |
|------|-------------|
| `app/Http/Controllers/V2/AndesIdentityController.php` | 6 endpoints identidad ANDES |
| `app/Http/Controllers/V2/CertificateRequestV2Controller.php` | Solicitud certificado vía PKI |

#### 2.3 Listeners exclusivos de ANDES

| Ruta | Descripción |
|------|-------------|
| `app/Listeners/LogAndesIdentityValidated.php` | Log evento identidad |
| `app/Listeners/SendAndesCertificateEmittedNotification.php` | Notificación certificado |

#### 2.4 Notifications exclusivas de ANDES

| Ruta | Descripción |
|------|-------------|
| `app/Notifications/AndesCertificateEmittedNotification.php` | Email certificado emitido |

#### 2.5 Webhook Events exclusivos de ANDES

| Ruta | Descripción |
|------|-------------|
| `app/Webhooks/Events/AndesCertificateEmittedWebhookEvent.php` | Webhook certificado |
| `app/Webhooks/Events/AndesIdentityValidatedWebhookEvent.php` | Webhook identidad |

#### 2.6 Provider ANDES

| Ruta | Descripción |
|------|-------------|
| `app/Providers/AndesServiceProvider.php` | Bindings IoC del módulo ANDES |

#### 2.7 Configuración

| Ruta | Descripción |
|------|-------------|
| `config/andes.php` | Variables de configuración ANDES ID + PKI |

#### 2.8 Tests exclusivos de ANDES

| Ruta | Descripción |
|------|-------------|
| `tests/Unit/Andes/AndesDataMapperTest.php` | Tests mapper |
| `tests/Unit/Andes/AndesIdentityServiceTest.php` | Tests servicio identidad |
| `tests/Unit/Andes/AndesPkiServiceTest.php` | Tests servicio PKI |
| `tests/Unit/Andes/AndesTokenManagerTest.php` | Tests gestor tokens |

#### 2.9 Documentación ANDES del proyecto raíz

| Ruta | Descripción |
|------|-------------|
| `docs/2026-04-20_impl-integracion-andes-wompi-cupos.md` | Plan de implementación Sprint |
| `docs/2026-04-20-GUIA-FRONTEND-ANGULAR-18-INTEGRACION-API-V2.md` | Guía frontend (parcial ANDES) |
| `andes/` (carpeta raíz) | PDFs comerciales/técnicos de ANDES |

---

### 🟡 MODIFICAR — Archivos mixtos (tienen secciones ANDES y no-ANDES)

#### 2.10 `config/app.php`
**Cambio:** Eliminar línea:
```php
App\Providers\AndesServiceProvider::class,
```
WompiServiceProvider **se mantiene**.

#### 2.11 `app/Providers/EventServiceProvider.php`
**Cambio:** Eliminar imports y bindings de:
- `AndesIdentityValidated` → `LogAndesIdentityValidated`
- `AndesCertificateEmitted` → `SendAndesCertificateEmittedNotification`

Los bindings de PaymentApproved/PaymentFailed (WOMPI) **se mantienen**.

#### 2.12 `routes/api-v2.php`
**Cambio:** Eliminar los grupos de rutas:
```
prefix('certificate-request')  → CertificateRequestV2Controller
prefix('andes/identity')        → AndesIdentityController (+ middleware AndesRateLimiter)
```
**Se mantienen:** `pricing`, `orders`, `admin/quotas`, `health`.

#### 2.13 `app/Http/Controllers/V2/HealthCheckController.php`
**Cambio:** Eliminar dependencia de `AndesHealthCheckService`. El health check queda solo con WOMPI.  
Resultado: constructor queda solo con `WompiPaymentService`. La respuesta de `services` elimina `andes_id_api` y `andes_pki`.

#### 2.14 `app/Http/Controllers/SwaggerDefinitions.php`
**Cambio:** Eliminar:
- Tags: `v2 - Identidad ANDES`, `v2 - Solicitudes ANDES`
- Schemas: `AndesCertificateRequest`, `AndesIdentityValidation`
- Mantener todos los demás tags y schemas.

#### 2.15 `app/Webhooks/Enums/WebhookEventType.php`
**Cambio:** Eliminar constantes:
```php
const ANDES_IDENTITY_VALIDATED    = 'andes.identity.validated';
const ANDES_CERTIFICATE_EMITTED   = 'andes.certificate.emitted';
```
Y actualizar el método `all()`.

#### 2.16 `tests/Unit/Listeners/NewEventListenersTest.php`
**Cambio:** Eliminar tests de `LogAndesIdentityValidated` y `SendAndesCertificateEmittedNotification`.  
Mantener tests de `SendPaymentApprovedNotification` y `SendPaymentFailedNotification`.

#### 2.17 `tests/Feature/V2EndpointsTest.php`
**Cambio:** Eliminar tests de endpoints ANDES identity y certificate-request v2.  
Mantener tests de orders, quotas, pricing, health.

---

### 🔴 MIGRACIONES — Decisión requerida

> ⚠️ **Las tablas ya existen en base de datos.** Antes de ejecutar rollback confirme si hay datos.

| Migración | Tabla/columna afectada | Acción propuesta |
|-----------|------------------------|------------------|
| `2026_04_21_000001_add_andes_code_to_identity_documents` | Columna `andes_code` en `identity_documents` | **Revertir** (DROP COLUMN) |
| `2026_04_21_000002_add_andes_cert_type_to_type_organization` | Columna `andes_cert_type` en `type_organization` | **Revertir** (DROP COLUMN) |
| `2026_04_21_000004_create_andes_certificate_requests_table` | Tabla `andes_certificate_requests` | **Revertir** (DROP TABLE) |
| `2026_04_21_000005_create_andes_identity_validations_table` | Tabla `andes_identity_validations` | **Revertir** (DROP TABLE) |
| `2026_04_21_000003_add_provider_type_to_certificate_requests` | Columna `provider_type` en `certificate_requests` | ⚠️ **Evaluar**: la columna puede ser útil para futuras integraciones. Puede dejarse como `nullable` sin revertir. |

**Las siguientes migraciones NO se tocan** (son de WOMPI/Cuotas — se mantienen):
- `000006_create_certificate_quotas_table`
- `000007_create_certificate_orders_table`
- `000008_create_certificate_order_items_table`
- `000009_create_payment_transactions_table`

---

### ✅ MANTENER INTACTO — Módulos independientes de ANDES

| Módulo/Ruta | Descripción |
|-------------|-------------|
| `app/Payments/` (completo) | WOMPI: contratos, DTOs, events, jobs, models, services, webhook |
| `app/Quotas/` (completo) | Órdenes, cuotas, pricing, commands |
| `app/Providers/WompiServiceProvider.php` | Bindings IoC WOMPI |
| `routes/webhooks-external.php` | Webhook receptor WOMPI |
| `tests/Unit/Payments/WompiPaymentServiceTest.php` | Tests WOMPI |
| `tests/Unit/Quotas/PricingServiceTest.php` | Tests pricing |
| `tests/Unit/Quotas/QuotaServiceTest.php` | Tests cuotas |
| Todos los módulos v1 | Sin cambio |

---

## 3. Resumen de Impacto Cuantitativo

| Categoría | Archivos a eliminar | Archivos a modificar |
|-----------|--------------------:|---------------------:|
| Módulo `app/Andes/` | 28 | 0 |
| Controllers V2 | 2 | 0 |
| Listeners | 2 | 0 |
| Notifications | 1 | 0 |
| Webhook Events | 2 | 0 |
| Providers | 1 | 0 |
| Config | 1 | 1 (`app.php`) |
| Tests | 4 (Unit Andes) | 2 (Feature + Listeners) |
| Docs | 2 (+ carpeta andes/) | 0 |
| Routes | 0 | 1 (`api-v2.php`) |
| EventServiceProvider | 0 | 1 |
| HealthCheckController | 0 | 1 |
| SwaggerDefinitions | 0 | 1 |
| WebhookEventType Enum | 0 | 1 |
| **TOTAL** | **~43 archivos** | **~8 archivos** |

---

## 4. Impacto en Suite de Tests

| Estado actual | Post-limpieza estimado |
|---------------|------------------------|
| 264 tests / 480 assertions | ~224 tests / ~400 assertions |
| 0 failures | 0 failures esperados |

Los 40 tests eliminados son exclusivamente de ANDES. Los tests de WOMPI, Cuotas, Pagos, Webhooks, Notificaciones, v1 **no se ven afectados**.

---

## 5. Riesgos Identificados

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| Rollback migraciones con datos existentes | 🔴 Alta | Verificar tablas vacías antes de `migrate:rollback` |
| Columna `provider_type` aún referenciada en código v1 | 🟡 Media | Auditar antes de drop |
| Guía Frontend Angular ya distribuida al equipo FE | 🟡 Media | Informar al equipo FE sobre cambios en endpoints v2 |

---

## 6. Próximos Pasos (requieren aprobación)

1. ✅ Aprobar este análisis
2. Ejecutar la limpieza de código (archivos + modificaciones)
3. ~~Generar Swagger actualizado post-limpieza~~ ✅ Hecho — versión 2.1.0 regenerada.

---

## ✅ Resultado Final (2026-04-21)

| Ítem | Resultado |
|------|-----------|
| Archivos eliminados | 43 archivos (app/Andes/, providers, controllers, listeners, notifications, events, tests, docs) |
| Archivos modificados | 8 archivos (config/app.php, EventServiceProvider, api-v2.php, HealthCheckController, WebhookEventType, SwaggerDefinitions, NewEventListenersTest, V2EndpointsTest) |
| Suite de tests | **224 passed / 403 assertions — 0 failures / 3 skipped** |
| Swagger | Regenerado — versión 2.1.0 sin tags/schemas ANDES |
| Commit | `refactor(andes): remove ANDES SCD integration — keep WOMPI + Quotas modules` (0da76c2) |
| 53 archivos en commit | 276 inserciones / 5.641 eliminaciones |

### ⚠️ Pendiente de decisión manual

Las siguientes **migraciones de BD** crean tablas/columnas ANDES que siguen existiendo en la base de datos. Se requiere tu decisión antes de hacer rollback:

| Migración | Acción al revertir |
|-----------|--------------------|
| `2026_04_21_000001` | DROP COLUMN `andes_code` en `identity_documents` |
| `2026_04_21_000002` | DROP COLUMN `andes_cert_type` en `type_organization` |
| `2026_04_21_000004` | DROP TABLE `andes_certificate_requests` |
| `2026_04_21_000005` | DROP TABLE `andes_identity_validations` |

> Si deseas revertirlas: `php artisan migrate:rollback --step=N` o eliminar los archivos de migración si la BD está en estado limpio.

---

**Commit ejecutado:**  
`refactor(andes): remove ANDES SCD integration — keep WOMPI + Quotas modules` — hash `0da76c2`

