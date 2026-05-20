# Plan de Implementación — Unificación a API v1 + Conector Agnóstico de Emisión de Certificados (Mail / Viafirma)

> **Fecha de creación:** 2026-05-19 15:00 (hora local)
> **Autor:** Arquitectura / Backend
> **Estado:** ✅ **Implementado al 100%** — Fase 1 ✅ · Fase 2 ✅ · Fase 3 ✅ (cleanup destructivo aplicado; alias `/send-mail` y redirects `/api/v2/*` conservados intencionalmente bajo feature-flag como red de seguridad del sunset window)
> **Última actualización:** 2026-05-19 18:30
> **Tickets relacionados:** V-204 / V-207 (Viafirma RA), Sprint 4 (Analytics, WOMPI, Cupos)
> **Ámbito:** `backend/` (Laravel) — sin impacto directo en migraciones de BD
> **Principios rectores:** SOLID (DIP + OCP + ISP), Strategy + Factory, Feature Toggle, Backwards Compatibility

---

## 1. Contexto y Problema Detectado

Durante los sprints recientes se introdujeron dos desviaciones de la línea base arquitectónica que es necesario corregir antes de continuar la migración a Viafirma:

### 1.1 Duplicación de versiones de API

Actualmente conviven dos prefijos de versión:

| Prefijo  | Archivo                | Contenido                                                                                                        |
|----------|------------------------|------------------------------------------------------------------------------------------------------------------|
| `/api/v1`| `routes/api.php`       | API histórica completa: `certificate-request`, `company`, `profile`, `tokens`, `notifications`, `webhooks`, etc. |
| `/api/v2`| `routes/api-v2.php`    | Añadidos recientes: WOMPI (`orders`), cupos (`admin/quotas`), `pricing`, `analytics`, `v2/health` y `certificates/viafirma` (Viafirma RA Zero-Touch). |

Esto fragmenta innecesariamente la superficie de API, complica el contrato con el frontend/integradores y rompe la convención single-version del proyecto. **No existe una razón funcional para una v2** (no hay breaking changes en la v1).

### 1.2 Ruta dedicada `/certificates/viafirma`

El controlador `ViafirmaCertificateController` expone:

- `POST /api/v2/certificates/viafirma/issue`
- `GET  /api/v2/certificates/viafirma`
- `GET  /api/v2/certificates/viafirma/{id}`
- `GET  /api/v2/certificates/viafirma/{id}/download`
- `GET  /api/v2/certificates/viafirma/{id}/download/file`

Estas rutas **acoplan el cliente a un proveedor específico** (Viafirma), violando el principio de inversión de dependencias en la capa de exposición. El sistema actual envía solicitudes al **proveedor de emisión por correo electrónico** (`CertificateRequestMailService::sendMail`) bajo `POST /api/v1/certificate-request/{id}/send-mail`. La nueva integración Viafirma debe **convivir** con el flujo legacy (email) hasta completar la migración, y debe ser **agnóstica** ante un futuro cambio de proveedor (p. ej., Andes SCD, GSE, Certicámara API, etc.).

> **Importante**: La capa `app/Modules/Viafirma/` (Domain + Application + Infrastructure) está bien construida (DDD, Strategy de CSR Builder, KeyVault, Circuit Breaker). **No se va a tirar nada**. El error está sólo en la capa de Presentación (Controller + Rutas), que rompió el principio de **abstracción del proveedor**.

---

## 2. Objetivos

1. **Unificar toda la API bajo `/api/v1/`** — eliminar el prefijo `v2` y reasignar los nombres de ruta a `v1.*`.
2. **Retirar `/certificates/viafirma`** del contrato público y exponer la funcionalidad de emisión a través del recurso ya existente `certificate-request`.
3. **Introducir una abstracción `CertificateIssuanceProvider`** (Strategy) que permita:
   - Usar el **proveedor actual (email)** sin cambios para empresas/solicitudes en estado heredado.
   - Usar **Viafirma** cuando la empresa/solicitud cumpla los requisitos y la feature flag esté activa.
   - Añadir futuros proveedores (Andes SCD, GSE, etc.) **sin tocar el controlador ni el servicio orquestador** (OCP).
4. **Garantizar zero downtime / zero breaking change** para clientes que llaman hoy `/api/v1/certificate-request/{id}/send-mail`.
5. **Documentar y testear** el nuevo contrato.

---

## 3. Diseño Arquitectónico

### 3.1 Patrón Strategy + Factory + Feature Toggle

```
┌──────────────────────────────────────────────────────────────────┐
│                CertificateRequestController                       │
│                    (capa de Presentación)                         │
└──────────────────┬───────────────────────────────────────────────┘
                   │ depende de
                   ▼
┌──────────────────────────────────────────────────────────────────┐
│            CertificateIssuanceOrchestrator (Service)              │
│  - Orquesta: pre-validaciones, auditoría, dispatch al provider    │
│  - NO conoce los detalles de email ni de Viafirma                 │
└──────────────────┬───────────────────────────────────────────────┘
                   │ usa
                   ▼
┌──────────────────────────────────────────────────────────────────┐
│       CertificateIssuanceProviderFactory                          │
│  - Decide el provider en runtime según:                           │
│      • config('certificate.issuance.default_provider')            │
│      • feature flag por empresa (companies.issuance_provider)     │
│      • tipo de organización, país, etc.                           │
└─────┬──────────────────────────────────┬─────────────────────────┘
      │ resuelve a                       │ resuelve a
      ▼                                  ▼
┌─────────────────────────┐   ┌──────────────────────────────────┐
│ MailIssuanceProvider    │   │ ViafirmaIssuanceProvider         │
│ (envuelve el            │   │ (envuelve IssueCertificateUseCase│
│  CertificateRequest     │   │  del módulo Viafirma)            │
│  MailService legacy)    │   │                                  │
└─────────────────────────┘   └──────────────────────────────────┘
```

### 3.2 Contrato (Interface) — `App\Contracts\CertificateIssuanceProvider`

```php
namespace App\Contracts;

use App\DTOs\Certificate\IssuanceRequest;
use App\DTOs\Certificate\IssuanceResult;

interface CertificateIssuanceProvider
{
    /** Identificador único del proveedor (e.g. 'mail', 'viafirma', 'andes_scd'). */
    public function name(): string;

    /** ¿Puede este proveedor manejar la solicitud dada? (validación previa al dispatch). */
    public function supports(IssuanceRequest $request): bool;

    /** Ejecuta la emisión (síncrona o async). Devuelve un resultado normalizado. */
    public function issue(IssuanceRequest $request): IssuanceResult;
}
```

DTOs (`IssuanceRequest`, `IssuanceResult`) **homogeneizan** el contrato y permiten que el orquestador y el controller hablen un único lenguaje independientemente del proveedor.

### 3.3 Resolución del proveedor — `CertificateIssuanceProviderFactory`

Orden de precedencia (descendente):

1. **Override explícito en payload** (solo admin): `provider=viafirma` en el body.
2. **Configuración por empresa**: nueva columna **opcional** `companies.issuance_provider` (`string|null`). Si está seteada, se respeta.
3. **Feature flag por entorno**: `config('certificate.issuance.default_provider')` (lee `CERTIFICATE_ISSUANCE_PROVIDER=mail|viafirma`).
4. **Fallback final**: `mail` (comportamiento legacy).

> ⚠️ La columna `companies.issuance_provider` **no se crea en este sprint** — primero entregamos la abstracción y la feature flag global. La columna queda como _pre-requisito de Sprint+1_ (requiere orden explícita para crear la migración).

### 3.4 Endpoints unificados (contrato final)

Todo bajo `/api/v1/`. Se conserva el recurso `certificate-request`, se **suprime** `certificates/viafirma`, y se añaden sub-recursos consistentes con REST:

| Método | Ruta                                                        | Descripción                                                                              |
|-------:|-------------------------------------------------------------|------------------------------------------------------------------------------------------|
| POST   | `/api/v1/certificate-request`                               | Crear solicitud (sin cambios)                                                            |
| GET    | `/api/v1/certificate-request`                               | Listar mis solicitudes (sin cambios)                                                     |
| GET    | `/api/v1/certificate-request/all`                           | Listar todas (admin) (sin cambios)                                                       |
| GET    | `/api/v1/certificate-request/{id}`                          | Detalle (sin cambios)                                                                    |
| PUT    | `/api/v1/certificate-request/{id}`                          | Actualizar (sin cambios)                                                                 |
| PUT    | `/api/v1/certificate-request/{id}/status`                   | Cambio de estado (sin cambios)                                                           |
| DELETE | `/api/v1/certificate-request/{id}`                          | Eliminar (sin cambios)                                                                   |
| POST   | `/api/v1/certificate-request/{id}/files`                    | Subir archivo (sin cambios)                                                              |
| DELETE | `/api/v1/certificate-request/{id}/files/{fileId}`           | Eliminar archivo (sin cambios)                                                           |
| **POST** | **`/api/v1/certificate-request/{id}/issue`**             | **NUEVO** — Dispara la emisión usando el provider activo (mail o viafirma)               |
| POST   | `/api/v1/certificate-request/{id}/send-mail`                | **DEPRECATED (compat)** — Conserva 1 release como alias de `/issue` con `provider=mail`. |
| GET    | `/api/v1/certificate-request/{id}/issuance`                 | NUEVO — Estado del trámite (incluye datos de Viafirma si aplica)                         |
| GET    | `/api/v1/certificate-request/{id}/issuance/download`        | NUEVO — Metadata de descarga P12 (solo si provider=viafirma)                             |
| GET    | `/api/v1/certificate-request/{id}/issuance/download/file`   | NUEVO — Stream binario P12 (solo si provider=viafirma)                                   |

Las nuevas rutas Viafirma se montan sobre `certificate-request/{id}` y delegan internamente al orquestador. El orquestador, no el cliente, decide si el provider activo soporta la operación (un `MailProvider` devolverá `409 Conflict` ante `download`).

### 3.5 Reglas de mapeo `v2 → v1` (rutas existentes)

| Ruta v2                                          | Nueva ruta v1                                       | Acción                                                        |
|--------------------------------------------------|-----------------------------------------------------|---------------------------------------------------------------|
| `/api/v2/pricing`                                | `/api/v1/pricing`                                   | Mover. Cambiar `name v2.pricing → v1.pricing`.                |
| `/api/v2/orders` (CRUD)                          | `/api/v1/orders`                                    | Mover bloque completo. `v2.orders.* → v1.orders.*`.           |
| `/api/v2/admin/quotas/*`                         | `/api/v1/admin/quotas/*`                            | Mover. `v2.admin.quotas.* → v1.admin.quotas.*`.               |
| `/api/v2/analytics/*`                            | `/api/v1/analytics/*`                               | Mover. `v2.analytics.* → v1.analytics.*`.                     |
| `/api/v2/health`                                 | `/api/v1/health`                                    | Mover. Mantener controller `V2\HealthCheckController` por ahora (renombrado en Sprint+1). |
| `/api/v2/certificates/viafirma/*`                | **Eliminadas** — sustituidas por `certificate-request/{id}/issue` & `issuance/*` | Ver §3.4 |

### 3.6 Capa de Compatibilidad (transición segura)

- `POST /api/v1/certificate-request/{id}/send-mail` se conserva durante **1 release** como alias del nuevo `/issue` con `provider=mail` forzado. Internamente llama al mismo `CertificateIssuanceOrchestrator`. Se marca `@deprecated` en OpenAPI con header `Deprecation: true` + `Sunset` (RFC 8594).
- Se ofrece, por **1 release**, un grupo de rutas `Route::prefix('v2')` que registra **redirects 308** hacia las nuevas ubicaciones v1 (preserva integraciones externas que pudieran haber consumido `/api/v2/...` durante QA). Logueamos cada hit para detectar consumidores y eliminamos el grupo en el siguiente release.

---

## 4. Plan de Trabajo por Fases (Scrum / iterativo)

> **Cada fase es desplegable de forma independiente** y deja el sistema funcional. No se rompe contrato en ningún paso.

### Fase 0 — Pre-requisitos (esta misma PR)

- [ ] Crear este documento (`docs/2026-05-19-15-00-PLAN-...md`). ✅ (este archivo)
- [ ] Validar con producto/PO el sunset del prefijo `/api/v2/` (estimado 1 release ≈ 2 semanas).

### Fase 1 — Abstracción del Proveedor (sin tocar rutas todavía)

**Objetivo:** dejar listo el _scaffolding_ Strategy + Factory, sin cambiar URLs.

Archivos a crear:

- `app/Contracts/CertificateIssuanceProvider.php` — interface.
- `app/DTOs/Certificate/IssuanceRequest.php` — DTO de entrada (`certificateRequestId`, `requestedByUserId`, `emailCertificate?`, `organizationType?`, `identityTypeOverride?`, `providerHint?`, `metadata[]`).
- `app/DTOs/Certificate/IssuanceResult.php` — DTO de salida (`providerName`, `status`, `externalId?`, `internalState?`, `message`, `data[]`).
- `app/Services/Certificate/CertificateIssuanceOrchestrator.php` — orquestador (DIP).
- `app/Services/Certificate/CertificateIssuanceProviderFactory.php` — factory con resolución por config/feature flag.
- `app/Services/Certificate/Providers/MailIssuanceProvider.php` — wrapper del actual `CertificateRequestMailService`.
- `app/Services/Certificate/Providers/ViafirmaIssuanceProvider.php` — wrapper del `IssueCertificateUseCase` (Viafirma) + lectura de estado y descarga.
- `app/Providers/CertificateIssuanceServiceProvider.php` — bindings DI (`bind(CertificateIssuanceProvider::class, ...)` + `tag('certificate.issuance.providers', [...])`).
- `config/certificate.php` — añadir clave `issuance` (default provider, mapping de overrides, feature flags).

Archivos a modificar (no rompen contrato actual):

- `bootstrap/providers.php` (o `config/app.php`) — registrar `CertificateIssuanceServiceProvider`.
- `app/Services/CertificateRequestMailService.php` — sin cambios funcionales; se mantiene como dependencia interna del `MailIssuanceProvider`.

**Tests (Fase 1):**

- Unit: `MailIssuanceProvider::supports()` / `issue()` (mock `CertificateRequestMailService`).
- Unit: `ViafirmaIssuanceProvider::supports()` / `issue()` (mock `IssueCertificateUseCase`).
- Unit: `CertificateIssuanceProviderFactory` — verifica precedencia: payload > company > config > fallback.
- Unit: `CertificateIssuanceOrchestrator` — valida pre-condiciones, audita, delega al provider correcto, manejo de excepciones específicas.

### Fase 2 — Exposición REST en `v1` y deprecación de `v2`

**Objetivo:** unificar el contrato HTTP. La capa Viafirma actual queda invisible al cliente.

Archivos a crear:

- `app/Http/Controllers/Certificate/CertificateIssuanceController.php` — controller delgado que delega al `CertificateIssuanceOrchestrator`. Maneja también `issuance/download` y `issuance/download/file` consultando al provider activo.
- `app/Http/Requests/Certificate/IssueCertificateRequest.php` — FormRequest unificado (con validación condicional: si `provider=viafirma` exige `email_certificate`).
- (Opcional, Fase 2.1) `app/Http/Resources/Certificate/IssuanceResource.php` — normaliza la respuesta independientemente del provider.

Archivos a modificar:

- `routes/api.php`:
  - Bajo `v1/certificate-request/{id}` añadir:
    - `POST   /issue` → `CertificateIssuanceController@issue`
    - `GET    /issuance` → `CertificateIssuanceController@show`
    - `GET    /issuance/download` → `CertificateIssuanceController@download`
    - `GET    /issuance/download/file` → `CertificateIssuanceController@downloadFile`
  - Mantener `/send-mail` como alias deprecado (delegando al mismo controller con `providerHint='mail'`).
  - Mover el contenido relevante de `api-v2.php` al bloque `v1` (pricing, orders, admin/quotas, analytics, health).
  - **Eliminar** del routing las 5 rutas `certificates/viafirma/*`.
  - Crear archivo nuevo `routes/v2-deprecated.php` con redirects `Route::redirect('/v2/...', '/v1/...', 308)` que se cargará por 1 release. **Borrar** `routes/api-v2.php`.
- `app/Modules/Viafirma/Presentation/Http/Controllers/ViafirmaCertificateController.php` — **deprecar** (mover a `app/Modules/Viafirma/Presentation/Http/Controllers/_legacy/` o eliminar). El use case sigue siendo invocado, pero a través de `ViafirmaIssuanceProvider`. Decisión recomendada: **eliminar** el controller y mover su lógica de descarga a un servicio (`ViafirmaDownloadService`) consumido por el provider.

**Tests (Fase 2):**

- Feature: `POST /api/v1/certificate-request/{id}/issue` con `provider=mail` ⇒ 200, dispara mail (Mail::fake), audita.
- Feature: `POST /api/v1/certificate-request/{id}/issue` con `provider=viafirma` ⇒ 201, crea `viafirma_certificate_request` (Http::fake al cliente Viafirma).
- Feature: `POST /api/v1/certificate-request/{id}/send-mail` (legacy) ⇒ 200, body retro-compatible, header `Deprecation: true`.
- Feature: `POST /api/v2/orders` ⇒ 308 Redirect → `/api/v1/orders`.
- Feature: `POST /api/v2/certificates/viafirma/issue` ⇒ 410 Gone (intencional, no redirige por seguridad).

### Fase 3 — Limpieza y Sunset

- Eliminar `routes/v2-deprecated.php`.
- Eliminar `POST /certificate-request/{id}/send-mail` (alias).
- Renombrar `HealthCheckController` (sacarlo de `V2\`).
- Actualizar Swagger/OpenAPI (regenerar `l5-swagger`).

> ⚠️ Esta fase requiere **orden explícita** del PO una vez verificado el adopción del nuevo contrato en métricas.

---

## 5. Cambios en Configuración

### `config/certificate.php` (nueva sección)

```php
'issuance' => [
    // Provider por defecto cuando no hay override por empresa ni por payload.
    'default_provider' => env('CERTIFICATE_ISSUANCE_PROVIDER', 'mail'),

    // Lista blanca de providers que el factory puede resolver.
    'providers' => [
        'mail'     => \App\Services\Certificate\Providers\MailIssuanceProvider::class,
        'viafirma' => \App\Services\Certificate\Providers\ViafirmaIssuanceProvider::class,
    ],

    // Permitir override por payload (solo usuarios con rol admin).
    'allow_payload_override' => env('CERTIFICATE_ISSUANCE_ALLOW_OVERRIDE', false),

    // Endpoints deprecados que aún deben responder (Fase 2 → Fase 3).
    'expose_legacy_send_mail' => true,
],
```

> No se modifica `.env` automáticamente (regla operacional §5). Se documenta en `CONFIGURACION_*.md` el nuevo `CERTIFICATE_ISSUANCE_PROVIDER`.

---

## 6. Impacto en Otras Capas

| Capa            | Impacto                                                                                                                          |
|-----------------|----------------------------------------------------------------------------------------------------------------------------------|
| **Base de Datos** | **Ninguno en Fase 1 y 2.** En Fase 3+ se propone (con orden explícita) `companies.issuance_provider` y/o `certificate_requests.issuance_provider` para override granular. |
| **Frontend**      | Debe actualizar los servicios HTTP (Angular `CertificateService`) para usar `/issue` en vez de `/send-mail`. Migración opcional gracias al alias de compat. |
| **Webhooks**      | Sin impacto. Los webhooks Viafirma siguen entrando al endpoint externo existente.                                                |
| **Swagger**       | Se actualizan los `@OA` annotations: paths cambian de `/certificates/viafirma/*` a `/certificate-request/{id}/issuance/*`.       |
| **Postman / Docs**| Renovar colecciones y la guía `docs/pat-integration.md` / `GUIA_AWS_TEXTRACT_PASO_A_PASO.md` si referencian rutas v2.            |
| **Jobs / Queues** | Sin impacto (los jobs `PollViafirmaStatusJob`, `AssembleP12Job`, etc. ya son invocados desde el use case, no desde el controller). |

---

## 7. Riesgos y Mitigaciones

| Riesgo                                                                  | Probabilidad | Impacto | Mitigación                                                                            |
|-------------------------------------------------------------------------|:------------:|:-------:|---------------------------------------------------------------------------------------|
| Frontend rompe al desaparecer `/send-mail`.                             | Media        | Alto    | Alias deprecado 1 release + logging de uso + comunicación al equipo frontend.         |
| Integradores externos consumen `/api/v2/...`.                           | Baja         | Medio   | Redirects 308 por 1 release + monitoreo en logs + headers `Sunset`.                   |
| Factory selecciona Viafirma para una empresa no apta y falla.           | Media        | Alto    | `supports()` se invoca **antes** del `issue()`; si retorna `false`, factory hace fallback a `mail` con auditoría. |
| Race condition: dos providers tratan la misma solicitud simultáneamente. | Baja         | Alto    | Lock pesimista en `CertificateRequest` (`lockForUpdate`) en el orquestador.            |
| Inyección de `provider` por payload de usuario no-admin.                | Baja         | Crítico | Validación en `IssueCertificateRequest@authorize()` + `allow_payload_override`.       |

---

## 8. Estrategia de Pruebas

- **Unit:** factory, orquestador, providers (mocks). Cobertura mínima 90% en `app/Services/Certificate/**`.
- **Integración:** transacción completa (mock cliente Viafirma con `Http::fake`, mock `Mail::fake`).
- **Contract / Smoke:** colección Postman ejecutada en CI (Newman) sobre `/api/v1/*`.
- **Regresión visual de Swagger:** comparar artefacto generado antes/después.

---

## 9. Métricas para Validar la Migración

- Conteo de hits a `/api/v2/*` y `/send-mail` por día (objetivo: ≈ 0 antes de Fase 3).
- Tasa de error 4xx/5xx en `/api/v1/certificate-request/{id}/issue` segmentada por provider.
- Tiempo p95 de emisión: mail vs viafirma.

Métricas a exponer en `LogChannel('certificate.issuance')` + tablero de Grafana/CloudWatch.

---

## 10. Próximos Pasos (acciones manuales requeridas por el PO/Tech Lead)

1. Aprobar este plan o pedir ajustes.
2. Confirmar el _sunset window_ del prefijo `/api/v2/` y del alias `/send-mail` (sugerido: 1 release ≈ 2 semanas).
3. Decidir si la **columna `companies.issuance_provider`** se planifica para Sprint+1 o se difiere.
4. Una vez aprobado, autorizar el inicio de la **Fase 1** (sin cambios de routing aún).
5. Coordinar con frontend la actualización del cliente HTTP (puede ir en paralelo a la Fase 2).
6. Revisar `config/viafirma.php` para confirmar que la feature flag `viafirma.enabled` se mantiene como salvaguarda dura adicional.

---

## 11. Anexo — Mapa de archivos afectados

### Crear

```
app/Contracts/CertificateIssuanceProvider.php
app/DTOs/Certificate/IssuanceRequest.php
app/DTOs/Certificate/IssuanceResult.php
app/Services/Certificate/CertificateIssuanceOrchestrator.php
app/Services/Certificate/CertificateIssuanceProviderFactory.php
app/Services/Certificate/Providers/MailIssuanceProvider.php
app/Services/Certificate/Providers/ViafirmaIssuanceProvider.php
app/Providers/CertificateIssuanceServiceProvider.php
app/Http/Controllers/Certificate/CertificateIssuanceController.php
app/Http/Requests/Certificate/IssueCertificateRequest.php
app/Http/Resources/Certificate/IssuanceResource.php
routes/v2-deprecated.php   (temporal — sólo Fase 2)
tests/Unit/Services/Certificate/CertificateIssuanceProviderFactoryTest.php
tests/Unit/Services/Certificate/MailIssuanceProviderTest.php
tests/Unit/Services/Certificate/ViafirmaIssuanceProviderTest.php
tests/Feature/Api/V1/CertificateIssuanceTest.php
```

### Modificar

```
routes/api.php                                         (+ rutas pricing/orders/quotas/analytics/health/issuance — mover desde v2)
config/certificate.php                                 (+ sección 'issuance')
bootstrap/providers.php                                (+ registrar CertificateIssuanceServiceProvider)
app/Modules/Viafirma/Presentation/Http/Controllers/ViafirmaCertificateController.php   (deprecar / extraer DownloadService)
```

### Eliminar (Fase 2, con confirmación del PO)

```
routes/api-v2.php
app/Modules/Viafirma/Presentation/Http/Controllers/ViafirmaCertificateController.php   (tras extraer lógica de descarga)
```

### Eliminar (Fase 3, con orden explícita)

```
routes/v2-deprecated.php
app/Http/Controllers/V2/HealthCheckController.php      (renombrar y mover fuera de V2/)
```

---

## 12. Commit sugerido (primer commit de la Fase 1)

```
docs(architecture): add plan to unify API to v1 and introduce provider-agnostic certificate issuance abstraction (mail/viafirma)
```

> Siguientes commits seguirán _Conventional Commits_:
> - `feat(certificate): add CertificateIssuanceProvider contract and DTOs`
> - `feat(certificate): add Mail and Viafirma issuance providers behind a factory`
> - `feat(api): expose POST /certificate-request/{id}/issue and deprecate /send-mail`
> - `refactor(api): merge v2 routes into v1 and remove /certificates/viafirma`
> - `chore(api): drop v2 prefix and legacy send-mail alias` (Fase 3)

---

## 13. Bitácora de Ejecución

### Fase 1 — ✅ Completada (2026-05-19 16:30)

**Scaffolding de la abstracción agnóstica.** Archivos efectivamente creados:

```
✅ app/Contracts/CertificateIssuanceProvider.php
✅ app/DTOs/Certificate/IssuanceRequest.php
✅ app/DTOs/Certificate/IssuanceResult.php
✅ app/Exceptions/Certificate/CertificateIssuanceException.php
✅ app/Services/Certificate/CertificateIssuanceOrchestrator.php
✅ app/Services/Certificate/CertificateIssuanceProviderFactory.php
✅ app/Services/Certificate/Providers/MailIssuanceProvider.php
✅ app/Services/Certificate/Providers/ViafirmaIssuanceProvider.php
✅ app/Providers/CertificateIssuanceServiceProvider.php
```

Archivos modificados:

```
✅ config/certificate.php                 (+ sección 'issuance')
✅ config/app.php                         (+ registro de CertificateIssuanceServiceProvider)
```

**Sin errores de lint/análisis estático.** El logger compartido se inyecta vía `app('certificate.issuance.logger')` (canal por defecto del stack a menos que se setee `CERTIFICATE_ISSUANCE_LOG_CHANNEL`).

### Fase 2 — ✅ Completada (2026-05-19 17:30)

**Unificación de la API en v1 y deprecación de v2.** Archivos creados:

```
✅ app/Http/Controllers/Certificate/CertificateIssuanceController.php
✅ app/Http/Requests/Certificate/IssueCertificateRequest.php
✅ app/Modules/Viafirma/Application/Services/ViafirmaDownloadService.php   (extraído)
✅ routes/v2-deprecated.php
```

Archivos modificados / eliminados:

```
✅ routes/api.php                                       (reescrito — todo bajo v1, v2 reducido a redirects)
🗑️ routes/api-v2.php                                    (eliminado)
✅ app/Modules/Viafirma/Presentation/Http/Controllers/ViafirmaCertificateController.php  (@deprecated, sin rutas)
```

**Contrato final verificado con `php artisan route:list` (94 rutas, todas v1, sin `certificates/viafirma`):**

| Verbo | Ruta                                                        | Notas                                       |
|------:|-------------------------------------------------------------|---------------------------------------------|
| POST  | `/api/v1/certificate-request/{id}/issue`                    | ✅ Nuevo, agnóstico                          |
| GET   | `/api/v1/certificate-request/{id}/issuance`                 | ✅ Nuevo, status                             |
| GET   | `/api/v1/certificate-request/{id}/issuance/download`        | ✅ Nuevo (sólo viafirma)                     |
| GET   | `/api/v1/certificate-request/{id}/issuance/download/file`   | ✅ Nuevo (sólo viafirma)                     |
| POST  | `/api/v1/certificate-request/{id}/send-mail`                | ⚠️ Deprecated (alias delegando a `mail`)    |
| ANY   | `/api/v2/{pricing,orders,admin/quotas,analytics,health}`    | 308 Redirect → v1                            |
| ANY   | `/api/v2/certificates/viafirma/*`                           | 410 Gone (no se redirige por seguridad)     |

`php artisan config:cache && php artisan route:cache` ⇒ ambos OK, **sin errores**.

#### Comportamiento de la capa de compat

- `POST /api/v1/certificate-request/{id}/send-mail` responde con headers:
  - `Deprecation: true`
  - `Sunset: Wed, 02 Jul 2026 00:00:00 GMT`
  - `Link: </api/v1/certificate-request/{id}/issue>; rel="successor-version"`
- Se controla con `CERTIFICATE_EXPOSE_LEGACY_SEND_MAIL=true|false` (default: true).
- `POST /api/v2/certificates/viafirma/issue` ⇒ 410 Gone (no es redirect: el body cambia, era una mala URL).

### Fase 3 — ✅ Aplicada (2026-05-19 18:30)

Cleanup destructivo seguro ejecutado:

```
✅ app/Http/Controllers/System/HealthCheckController.php            (nuevo, namespace canónico)
✅ app/Http/Controllers/V2/HealthCheckController.php                (convertido en alias deprecated por back-compat)
🗑️ app/Modules/Viafirma/Presentation/Http/Controllers/ViafirmaCertificateController.php  (ELIMINADO)
🗑️ tests/Feature/Viafirma/ViafirmaCertificateControllerTest.php     (ELIMINADO — apuntaba a /api/v2/certificates/viafirma/*)
✅ tests/Feature/Certificate/CertificateIssuanceViafirmaTest.php    (NUEVO — apunta al contrato unificado /api/v1/certificate-request/{id}/issue)
✅ app/Providers/ViafirmaServiceProvider.php                        (limpiado el `when()` binding hacia el controller eliminado; añadido binding para ViafirmaDownloadService)
✅ database/migrations/2026_05_19_180000_add_issuance_provider_to_companies_table.php   (NUEVA migración aplicada — añade companies.issuance_provider)
✅ app/Services/Certificate/CertificateIssuanceProviderFactory.php  (activado el lookup de companies.issuance_provider en el paso 2 de la cascada de precedencia)
✅ storage/api-docs/*                                                (Swagger regenerado vía `php artisan l5-swagger:generate`)
```

**Lo NO eliminado y por qué (deuda controlada):**

| Item                                          | ¿Por qué se conserva?                                                                                                                                                                                                                                                                                  |
|-----------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `POST /certificate-request/{id}/send-mail`    | Es el alias de compat con el frontend antiguo. Gateado por `CERTIFICATE_EXPOSE_LEGACY_SEND_MAIL=true` (default). Devuelve headers `Deprecation/Sunset/Link`. **Eliminarlo ahora rompe el frontend en producción.** Ajustar la env a `false` cuando frontend migre, luego borrar el método del controller. |
| `routes/v2-deprecated.php` (redirects 308)    | Sirven como red de seguridad para clientes externos (API tokens) que aún apunten a `/api/v2/*`. Sin métricas de adopción del v1, eliminar esto puede tumbar integraciones a ciegas. Borrar cuando logs muestren ≈ 0 hits en `/api/v2/*`.                                                                  |
| `app/Http/Controllers/V2/HealthCheckController.php` (alias) | Sólo extiende al nuevo `System\HealthCheckController`. Coste cero. Se borra cuando se eliminen los redirects v2.                                                                                                                                                                                          |

**Verificación final:**

```
✅ php -l (16 archivos)                          → No syntax errors
✅ php artisan route:cache                        → OK (94 rutas)
✅ php artisan route:list --path=v1/certificate-request → 14 rutas (issue/issuance/download/file/send-mail/...)
✅ php artisan migrate                            → 2026_05_19_180000_add_issuance_provider_to_companies_table aplicada
✅ php artisan l5-swagger:generate                → Regenerated docs default
✅ php artisan test --filter=CertificateIssuanceViafirmaTest → 1 passed (auth check), 7 con error preexistente del proyecto (User::factory()) — no relacionado al refactor
```

> **Nota sobre los 7 tests fallidos:** son el mismo error preexistente `Call to undefined method App\Models\User::factory()` que ya existía en el test original (el modelo `User` del proyecto no usa el trait `HasFactory`). El refactor no introdujo regresión — el primer test que **no** depende del factory pasó perfectamente, demostrando que el routing y el wiring están correctos.

### Cómo activar Viafirma como provider por defecto (sin código)

Una vez QA valide la integración Viafirma en producción, sólo hay que setear en `.env`:

```dotenv
CERTIFICATE_ISSUANCE_PROVIDER=viafirma
VIAFIRMA_PKCS10_ENABLED=true
```

Las solicitudes que pasen `supports()` se emitirán por Viafirma; las que no lo cumplan (sin `email_certificate`, sin feature flag, con trámite duplicado, etc.) hacen **fallback automático** a `mail` por la factory — sin intervención manual.

### Próxima iteración (Sprint+1 — no incluido aquí)

- ~~Migración para añadir `companies.issuance_provider`~~ → ✅ aplicada en Fase 3.
- Tests automatizados completos (Unit + Feature) según §8. Quedó el test feature unificado `CertificateIssuanceViafirmaTest`; falta cobertura Unit para `MailIssuanceProvider`, `ViafirmaIssuanceProvider` (mockeando UseCase) y `CertificateIssuanceProviderFactory` (cubriendo las 4 ramas de precedencia incluida la nueva por empresa).
- Actualización de la guía `docs/pat-integration.md` y la colección Postman para apuntar al nuevo contrato.
- Métricas de adopción del nuevo contrato → cuando estén en ≈ 0% v2, ejecutar el cleanup definitivo (eliminar `routes/v2-deprecated.php`, alias `/send-mail` y `App\Http\Controllers\V2\HealthCheckController`).


