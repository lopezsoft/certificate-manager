# cline-memory.md
> Archivo de persistencia de contexto — Generado: 2026-06-16 08:12 (America/Bogota)
> **NO modificar manualmente.** Se actualiza con cada sesión de análisis arquitectónico.

---

## 1. Stack y Arquitectura

| Capa | Detalle |
|---|---|
| **Framework** | Laravel 10 (`^v10.10.0`) · PHP 8.1+ |
| **Autenticación** | Laravel Passport 11 (OAuth2 / Personal Access Tokens) |
| **API** | REST bajo `/api/v1/` · OpenAPI 3 via `darkaonline/l5-swagger` |
| **Queue / Jobs** | Laravel Queue (async) · Jobs en `app/Jobs/` y `app/Modules/Viafirma/Infrastructure/Jobs/` |
| **Storage** | AWS S3 via `league/flysystem-aws-s3-v3` |
| **Reporting** | JasperPHP (`lopezsoft/jasperphp`) · Maatwebsite Excel · mPDF |
| **Facturación** | DIAN UBL 2.1 (`lopezsoft/ubl21dian`) |
| **Pagos** | Wompi (`WompiServiceProvider`) |
| **IA / OCR** | Gemini API (`gemini-api-php/client`) · Google Cloud Vision · AWS Textract |
| **Testing** | PHPUnit 10 · Mockery · Faker |
| **Contenedor** | Docker (`Dockerfile` + `compose.yaml`) |

### Organización de Capas

```
app/
├── Core/               # Modelos base: CoreModel, MasterModel, JReportModel
├── Http/Controllers/   # Controladores MVC tradicionales (API REST)
├── Services/           # Lógica de negocio (capa de aplicación MVC)
├── Models/             # Eloquent ORM
├── Repositories/       # Patrón Repository
├── Interfaces/         # Contratos: CrudInterface, DocumentsInterface, StatusProcessor, UploadToS3Contract
├── DTOs/               # Data Transfer Objects
├── Enums/              # Enumeraciones PHP 8.1
├── Jobs/               # Jobs asíncronos globales
├── Events/ + Listeners/# Event-Driven (Laravel Events)
├── Providers/          # Service Providers modulares
├── Modules/            # Módulos con arquitectura DDD (ver §2)
│   ├── Auth/
│   ├── Company/
│   ├── Settings/
│   └── Viafirma/       # Bounded Context completo (DDD)
├── Notifications/      # Laravel Notifications
├── Mail/               # Mailables
├── Exports/            # Maatwebsite Excel exports
├── Webhooks/           # Handlers de webhooks entrantes
├── Payments/           # Lógica de pagos
├── Validators/         # Validadores custom
├── Traits/             # Traits reutilizables
└── Common/             # Helper.php (autoloaded), HttpResponseMessages
```

### Convenciones Detectadas
- `declare(strict_types=1)` en todos los archivos del módulo Viafirma.
- Constructor Property Promotion + `readonly` (PHP 8.1).
- Inyección de dependencias vía constructor (DI Container de Laravel).
- Respuestas JSON estandarizadas via `HttpResponseMessages::getResponse()`.
- Caché de datos maestros con `Cache::remember()` (TTL 24h).
- Logging estructurado con claves `module.context.action` (ej. `viafirma.fsm.transition`).
- Swagger/OpenAPI annotations en controladores (`@OA\Get`, `@OA\Post`, etc.).
- Throttling por middleware nombrado: `certificate-create`, `certificate-issue`, `file-upload`, `token-create`.
- Middleware `admin` para rutas de administración.

---

## 2. Core del Sistema — Módulos y Servicios Clave

### 2.1 Módulo Viafirma (DDD — Bounded Context)
Ubicación: `app/Modules/Viafirma/`

| Subcapa | Componentes clave |
|---|---|
| **Domain** | `StateMachine` (FSM), Enums: `InternalState`, `RemoteStatus`, `CertificateProfile`, `IdentityType`, `OrganizationType`, `RevocationReason`; Events: `ViafirmaReadyToDownload`, `ViafirmaRequestFailed`, `ViafirmaStatusChanged`; Contracts, Mappers, Exceptions |
| **Application** | UseCases, Commands, DTOs, Services, Listeners, Notifications |
| **Infrastructure** | `ViafirmaCircuitBreaker` (Circuit Breaker), `SafePemLogger` (Decorator PSR-3), Jobs: `PollViafirmaStatusJob`, `AssembleP12Job`; Crypto, KeyVault, Http client, Persistence (Models: `ViafirmaCertificateRequest`, `ViafirmaStatusHistory`), Console commands |
| **Presentation** | `RevocationController`, `KycLinkController` |

**Providers:** `ViafirmaServiceProvider` registra el módulo completo.

### 2.2 Gestión de Certificados (MVC)
- `CertificateRequestService` — CRUD de solicitudes
- `CertificateProcessingService` — Procesamiento y validación
- `CertificateValidatorService` — Validaciones de negocio
- `CertificateIssuanceServiceProvider` — Emisión agnóstica de proveedor
- `QuotaService` — Control de cupos por empresa
- `PricingService` — Tarifas y pricing tiers
- Jobs: `AutoIssueViafirmaJob`, `RetryStalledIssuancesJob`, `ProcessCertificateJob`

### 2.3 OCR / Análisis de Documentos
- `UnifiedOcrService` — Orquestador unificado de OCR
- `AwsTextractService` — Integración AWS Textract
- `OcrService` — OCR genérico
- `DocumentAnalysisService` — Análisis de resultados
- `AiContentService` — Integración Gemini API (IA generativa)
- Modelo: `DocumentAnalysisResult`

### 2.4 Notificaciones y Reportes
- `SendExpiringCertificatesNotificationsJob` — Alertas de vencimiento
- `SendAdminExpiringCertificatesReportJob` — Reporte admin de vencimientos
- `SendMonthlyAdminCertificatesReportJob` — Reporte mensual admin
- `SendMonthlyCompanyCertificatesReportJob` — Reporte mensual por empresa
- `NotificationController` — Gestión de notificaciones de usuario

### 2.5 Datos Maestros (Cacheados)
- `ReferencedTablesService` → `MasterController`
  - `TypeOrganization`, `IdentityDocument`, `EntityDocumentType`
  - Cache TTL: 24h · Keys: `master.type_organization`, `master.identity_documents`, `master.entity_document_types`

### 2.6 Otros Servicios Globales
- `CompanyService` — Gestión de empresas
- `LocationService` — Datos geográficos
- `TableCrudService` + `TableValidationService` — CRUD genérico
- `ConsumeService` — Consumo de documentos
- `OrderService` — Gestión de órdenes
- `PaymentOrchestrator` — Orquestación de pagos (Wompi)
- `ZipExtractorService` — Extracción de archivos ZIP
- `GeneraSettingsService` — Configuración general

### 2.7 Configuraciones Clave (`config/`)
`viafirma.php`, `certificate.php`, `ai.php`, `tokens.php`, `webhooks.php`, `wompi.php`, `services.php`

---

## 3. Estado Actual

### 3.1 Componentes Analizados Recientemente

| Componente | Estado | Notas |
|---|---|---|
| `ReferencedTablesService` | ✅ Estable | Caché 24h, 3 endpoints maestros, sin dependencias externas |
| `MasterController` | ✅ Estable | DI constructor, Swagger documentado, delega 100% al service |
| `StateMachine` (Viafirma) | ✅ Activo | FSM con guard clauses, historial en `viafirma_status_history`, eventos de dominio |
| `ViafirmaCircuitBreaker` | ✅ Activo | CLOSED/OPEN/HALF_OPEN, threshold configurable, Redis en prod / file en local |
| `SafePemLogger` | ✅ Activo | Decorator PSR-3, redacta PEM/PKCS12/tokens/OAuth antes de loguear |
| `PollViafirmaStatusJob` | 🔄 En revisión | Job de polling de estado remoto Viafirma |
| `AssembleP12Job` | 🔄 En revisión | Ensamblado de certificado PKCS#12 |
| `RetryStalledIssuancesJob` | 🔄 En revisión | Reintento de emisiones atascadas |

### 3.2 Dependencias / Mejoras Pendientes

- **Sunset API v2:** Rutas `/api/v2/` marcadas como compatibilidad temporal (redirects 308). Plan documentado en `docs/2026-05-19-15-00-PLAN-UNIFICACION-API-V1-Y-PROVEEDOR-AGNOSTICO-VIAFIRMA.md`.
- **AiContentService:** Existe `AiContentService.php.backup` — indica refactorización en curso de la integración Gemini.
- **Emisión agnóstica:** `CertificateIssuanceServiceProvider` abstrae el proveedor; migración completa en progreso.
- **Tests:** Directorio `tests/` presente con PHPUnit 10; cobertura de módulo Viafirma pendiente de verificar.
- **Documentación IA:** Múltiples archivos `.md` en raíz (`AI_DOCUMENTATION.md`, `SISTEMA_IA_FINAL.md`, `IA_INTEGRATION_PLAN.md`) sugieren integración IA en fase de estabilización.
- **Viafirma KeyVault:** `Infrastructure/KeyVault/` — gestión de claves criptográficas; nivel de madurez por confirmar.

### 3.3 Rutas API Principales (`/api/v1/`)

| Recurso | Método | Descripción |
|---|---|---|
| `/certificate-request` | POST/GET/PUT/DELETE | CRUD solicitudes de certificado |
| `/certificate-request/{id}/issue` | POST | Emisión agnóstica (throttle) |
| `/certificate-request/{id}/issuance/download` | GET | Descarga de certificado |
| `/certificate-request/{id}/revoke` | POST | Revocación (Viafirma) |
| `/certificate-request/{id}/kyc-link` | GET | Link KYC biométrico |
| `/organization-type` | GET | Datos maestros (cacheado) |
| `/identity-documents` | GET | Datos maestros (cacheado) |
| `/entity-document-types` | GET | Datos maestros (cacheado) |
| `/company` | GET/PUT | Gestión empresa |
| `/quota/status` | GET | Estado de cupos |
| `/pricing` | GET | Tarifas |
| `/tokens` | CRUD | Personal Access Tokens |
| `/notifications` | GET/POST | Notificaciones de usuario |

---

*Última actualización: 2026-06-16 · Rama: `a8b52aa` (HEAD)*
