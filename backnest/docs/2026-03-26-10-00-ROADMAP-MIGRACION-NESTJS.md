# 🚀 ROADMAP DE MIGRACIÓN: Laravel → NestJS + Fastify + TypeORM

> **Fecha inicio:** 26 de marzo de 2026
> **Fecha actualización:** 26 de marzo de 2026 (auditoría completa)
> **Proyecto:** Certificate Manager Backend
> **Stack Origen:** Laravel 10.10 + PHP 8.1 + Eloquent + Passport OAuth2
> **Stack Destino:** NestJS v10 + Fastify + TypeORM 0.3 + PostgreSQL 16
> **Metodología:** SCRUM (Sprints de 2 semanas)
> **Principios:** SOLID, Clean Code, Clean Architecture
> **Estado global:** 🔴 ~72% completado — Rutas incompatibles con Laravel + 12 endpoints faltantes + ConsumeModule stub

---

## 🔴 AUDITÓRIA DE ESTADO REAL (26-03-2026)

> Resultado de revisión automática comparando archivos existentes contra contratos de rutas Laravel.

### Resumen ejecutivo de auditoría

| Categoría | Total | Implementado | Faltante |
|-----------|-------|-------------|---------|
| Entidades TypeORM | 23 | 23 ✅ | 0 |
| Módulos NestJS registrados | 15 | 15 ✅ (2 son stubs) | — |
| Archivos de configuración | 8 | 8 ✅ | 0 |
| Interceptors / Filters globales | 3 | 3 ✅ | 0 |
| Templates Handlebars (mail) | 4 | 4 ✅ | 0 |
| Endpoints Laravel compatibles | 57 | ~41 🟡 | ~16 ❌ |
| Rutas con path correcto (= Laravel) | — | ❌ Incompatibles | Ver tabla |
| Tests E2E | — | 0 ❌ | Todos |

---

### ❌ PROBLEMA CRÍTICO 1 — Rutas incompatibles con Laravel

`main.ts` activa `enableVersioning({ type: VersioningType.URI })` pero **ningún controller declara `@Version('1')`**. Además los controllers usan prefijos de módulo NestJS que no existen en el backend Laravel.

| Ruta Laravel (frontend Angular espera) | Ruta NestJS actual | ¿Compat? |
|---------------------------------------|-------------------|---------|
| `GET /api/v1/countries` | `GET /api/locations/countries` | ❌ |
| `GET /api/v1/departments` | `GET /api/locations/departments/:countryId` | ❌ |
| `GET /api/v1/cities` | `GET /api/locations/cities/:departmentId` | ❌ |
| `GET /api/v1/identity-documents` | `GET /api/master/identity-documents` | ❌ |
| `GET /api/v1/organization-type` | `GET /api/master/type-organizations` | ❌ (nombre distinto) |
| `GET /api/v1/company` | `GET /api/companies` | ❌ |
| `GET /api/v1/certificate-request` | `GET /api/certificates` | ❌ |
| `GET /api/v1/auth/logout` (GET) | `POST /api/auth/logout` | ❌ (verbo distinto) |
| `GET /api/v1/settings/reports` | `GET /api/settings/report-header` | ❌ (path distinto) |
| `PUT /api/v1/settings/reports/{id}` | `PUT /api/settings/report-header` | ❌ |

**Solución requerida:** agregar `@Version('1')` en todos los controllers Y ajustar los `@Controller()` base paths para que coincidan con las rutas Laravel (sin prefijo de módulo).

---

### ❌ PROBLEMA CRÍTICO 2 — Endpoints faltantes (12)

| Endpoint Laravel | Módulo NestJS | Estado |
|-----------------|---------------|--------|
| `GET /api/v1/verify-email/{id}/{hash}` | auth | ❌ No implementado |
| `POST /api/v1/email/verification-notification` | auth | ❌ No implementado |
| `POST /api/v1/certificate-request/{id}/send-mail` | certificates | ❌ No implementado |
| `GET /api/v1/webhooks/events` | webhooks | ❌ No implementado |
| `POST /api/v1/webhooks/{id}/rotate-secret` | webhooks | ❌ No implementado |
| `GET /api/v1/tokens/{id}` | tokens | ❌ No implementado |
| `POST /api/v1/tokens/{id}/renew` | tokens | ❌ No implementado |
| `GET /api/v1/certificates/expiring` | notifications | ❌ No implementado |
| `POST /api/v1/admin/certificates/notify-now` | notifications admin | ❌ No implementado |
| `GET /api/v1/consume/{year}` | consume | ❌ Módulo stub vacío |
| `GET /api/v1/consume/{year}/{month}` | consume | ❌ Módulo stub vacío |
| `PUT /api/v1/profile/{id}` | auth/users | ❌ No implementado |

---

### Estado real por sprint (auditado)

| Sprint | Módulo | Estado real | Notas |
|--------|--------|-------------|-------|
| Sprint 0 | 23 entidades + Common layer | ✅ Completo | Todos los archivos existen |
| Sprint 1 | Auth | 🟡 Parcial | Faltan: `verify-email`, `email-verification` |
| Sprint 2 | Locations + Master + Companies | 🟡 Parcial | Código ✅ pero rutas NO coinciden con Laravel |
| Sprint 3 | Certificates | 🟡 Parcial | Falta `send-mail`; path base incorrecto |
| Sprint 4 | Files | 🟡 Parcial | Path `/files/` en vez de `/certificate-request/{id}/files/` |
| Sprint 5 | Notifications + Mail + Templates | 🟡 Parcial | Faltan: `expiring`, `notify-now` |
| Sprint 6 | Webhooks | 🟡 Parcial | Faltan: `events`, `rotate-secret` |
| Sprint 7 | AI/OCR | ✅ Completo | — |
| Sprint 8 | Users + Scheduler + CRUD | 🟡 Parcial | Consume stub; falta `PUT /profile/{id}` |
| Sprint 9 | Tokens PAT | 🟡 Parcial | Faltan: `GET /:id`, `POST /:id/renew` |
| Sprint 10 | Docker + Infra | 🟡 En progreso | Docker ✅ — Tests / Swagger / CI-CD pendientes |

---

### Cobertura de infraestructura

- **Entidades TypeORM:** 23/23 ✅
- **Módulos NestJS registrados:** 15/15 ✅ (`consume` y `reports` son stubs)
- **Configuraciones:** 8/8 ✅
- **Interceptors globales:** 2/2 ✅ (`LaravelResponseInterceptor`, `LaravelPaginationInterceptor`)
- **Filtros globales:** 1/1 ✅ (`LaravelExceptionFilter`)
- **Docker:** ✅ Dockerfile multi-stage + docker-compose prod + docker-compose dev (+ Mailpit)
- **Migrations CLI:** ✅ `typeorm.config.ts` + scripts npm
- **Tests E2E:** 0/57 ❌
- **Swagger decorators completos:** ❌ Parcial (solo en CRUD module)
- **CI/CD pipeline:** ❌ Pendiente
- **Rutas compatibles con Laravel:** ❌ — Requieren `@Version('1')` + corrección de paths


## �📋 TABLA DE CONTENIDO

1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Análisis del Sistema Actual](#análisis-del-sistema-actual)
3. [Arquitectura Destino (Clean Architecture)](#arquitectura-destino)
4. [Convenciones y Estándares](#convenciones-y-estándares)
5. [Compatibilidad de Respuestas Laravel](#compatibilidad-de-respuestas-laravel)
6. [Product Backlog](#product-backlog)
7. [Sprint 0 — Fundación](#sprint-0)
8. [Sprint 1 — Autenticación y Usuarios](#sprint-1)
9. [Sprint 2 — Módulo de Empresas y Datos Maestros](#sprint-2)
10. [Sprint 3 — Módulo de Solicitudes de Certificados (Core)](#sprint-3)
11. [Sprint 4 — Gestión de Archivos y Storage](#sprint-4)
12. [Sprint 5 — Notificaciones y Email](#sprint-5)
13. [Sprint 6 — Webhooks y Eventos](#sprint-6)
14. [Sprint 7 — IA/OCR y Procesamiento Asíncrono](#sprint-7)
15. [Sprint 8 — Reportes, Exports y Tareas Programadas](#sprint-8)
16. [Sprint 9 — Tokens PAT y Seguridad Avanzada](#sprint-9)
17. [Sprint 10 — Testing E2E, QA y Deployment](#sprint-10)
18. [Riesgos y Mitigaciones](#riesgos-y-mitigaciones)
19. [Criterios de Aceptación Global](#criterios-de-aceptación-global)
20. [Definition of Done](#definition-of-done)

---

## 1. RESUMEN EJECUTIVO <a name="resumen-ejecutivo"></a>

### Alcance de la Migración

| Componente | Laravel (Actual) | NestJS (Destino) |
|-----------|-------------------|-------------------|
| **Endpoints API** | 50+ rutas REST v1 | Idénticas rutas, mismos verbos HTTP |
| **Modelos/Entidades** | 21 modelos Eloquent | 23 entidades TypeORM ✅ |
| **Servicios** | 18 servicios | 18+ servicios (Clean Architecture) |
| **Middleware** | 13 middleware | Guards, Interceptors, Pipes |
| **Jobs (Queue)** | 5 jobs async | Bull Queue workers |
| **Eventos** | 10 eventos + 4 listeners | EventEmitter2 |
| **Notificaciones** | 15 clases | Módulo Notification custom |
| **Mails** | 3 mailables | Nodemailer + Templates |
| **Webhooks** | Módulo completo | Módulo NestJS |
| **Auth** | Passport OAuth2 + PAT | JWT + Passport.js + PAT |
| **Tests** | 165 tests | Jest + Supertest |

### Restricción Fundamental

> **Las rutas, parámetros de query, cuerpos de request, y estructura de respuestas JSON DEBEN ser idénticas al backend Laravel.** El frontend Angular NO debe requerir cambios.

---

## 2. ANÁLISIS DEL SISTEMA ACTUAL <a name="análisis-del-sistema-actual"></a>

### 2.1 Inventario de Endpoints por Módulo

#### Públicos (Sin Auth) — 5 endpoints
| Método | Ruta | Controller | Acción |
|--------|------|-----------|--------|
| GET | `/api/v1/countries` | LocationController | getCountries |
| GET | `/api/v1/departments` | LocationController | getDepartments |
| GET | `/api/v1/cities` | LocationController | getCities |
| GET | `/api/v1/identity-documents` | MasterController | getIdentityDocuments |
| GET | `/api/v1/organization-type` | MasterController | getTypeOrganization |

#### Autenticación — 8 endpoints
| Método | Ruta | Controller | Acción |
|--------|------|-----------|--------|
| POST | `/api/v1/auth/login` | AuthController | login |
| GET | `/api/v1/auth/logout` | AuthController | logout |
| GET | `/api/v1/auth/user` | AuthController | user |
| POST | `/api/v1/register` | RegisteredUserController | store |
| POST | `/api/v1/forgot-password` | PasswordResetLinkController | store |
| POST | `/api/v1/reset-password` | NewPasswordController | store |
| GET | `/api/v1/verify-email/{id}/{hash}` | VerifyEmailController | verify |
| POST | `/api/v1/email/verification-notification` | EmailVerificationNotificationController | store |

#### CRUD Genérico — 5 endpoints
| Método | Ruta | Controller | Acción |
|--------|------|-----------|--------|
| GET | `/api/v1/crud` | TableCrudController | index |
| POST | `/api/v1/crud` | TableCrudController | store |
| GET | `/api/v1/crud/{id}` | TableCrudController | show |
| PUT | `/api/v1/crud/{id}` | TableCrudController | update |
| DELETE | `/api/v1/crud/{id}` | TableCrudController | destroy |

#### Solicitudes de Certificados — 10 endpoints
| Método | Ruta | Controller | Middleware Extra |
|--------|------|-----------|-----------------|
| POST | `/api/v1/certificate-request` | CertificateRequestController | throttle:cert-create, validate.mime |
| GET | `/api/v1/certificate-request` | CertificateRequestController | — |
| GET | `/api/v1/certificate-request/all` | CertificateRequestController | — |
| GET | `/api/v1/certificate-request/{id}` | CertificateRequestController | — |
| PUT | `/api/v1/certificate-request/{id}` | CertificateRequestController | — |
| PUT | `/api/v1/certificate-request/{id}/status` | CertificateRequestController | — |
| DELETE | `/api/v1/certificate-request/{id}` | CertificateRequestController | — |
| POST | `/api/v1/certificate-request/{id}/send-mail` | CertificateRequestController | throttle:send-mail |
| POST | `/api/v1/certificate-request/{id}/files` | CertificateRequestFilesController | throttle:file-upload, validate.mime |
| DELETE | `/api/v1/certificate-request/{id}/files/{fileId}` | CertificateRequestFilesController | — |

#### Empresa — 3 endpoints
| Método | Ruta | Controller | Acción |
|--------|------|-----------|--------|
| GET | `/api/v1/company` | CompanyController | read |
| GET | `/api/v1/company/settings` | CompanyController | getSetting |
| PUT | `/api/v1/company/settings` | CompanyController | updateSetting |

#### Perfil — 3 endpoints
| Método | Ruta | Controller | Acción |
|--------|------|-----------|--------|
| GET | `/api/v1/profile` | AuthController | user |
| GET | `/api/v1/profile/types` | AuthController | types |
| PUT | `/api/v1/profile/{id}` | AuthController | updateUser |

#### Consumo — 2 endpoints
| Método | Ruta | Controller | Acción |
|--------|------|-----------|--------|
| GET | `/api/v1/consume/{year}` | ConsumeController | readByYear |
| GET | `/api/v1/consume/{year}/{month}` | ConsumeController | readByMonth |

#### Reportes — 2 endpoints
| Método | Ruta | Controller | Acción |
|--------|------|-----------|--------|
| GET | `/api/v1/settings/reports` | ReportsHeaderController | getData |
| PUT | `/api/v1/settings/reports/{id}` | ReportsHeaderController | update |

#### Tokens PAT — 6 endpoints
| Método | Ruta | Controller | Acción |
|--------|------|-----------|--------|
| GET | `/api/v1/tokens` | TokenController | index |
| POST | `/api/v1/tokens` | TokenController | store |
| GET | `/api/v1/tokens/{id}` | TokenController | show |
| DELETE | `/api/v1/tokens/{id}` | TokenController | destroy |
| POST | `/api/v1/tokens/{id}/renew` | TokenController | renew |
| POST | `/api/v1/tokens/revoke-all` | TokenController | revokeAll |

#### Notificaciones y Certificados — 4 endpoints
| Método | Ruta | Controller | Acción |
|--------|------|-----------|--------|
| GET | `/api/v1/certificates/expiring` | NotificationController | expiring |
| GET | `/api/v1/notifications` | NotificationController | index |
| POST | `/api/v1/notifications/read-all` | NotificationController | markAllAsRead |
| POST | `/api/v1/notifications/{id}/read` | NotificationController | markAsRead |

#### Admin — 1 endpoint
| Método | Ruta | Controller | Acción |
|--------|------|-----------|--------|
| POST | `/api/v1/admin/certificates/notify-now` | NotificationController | triggerNow |

#### Webhooks — 8 endpoints
| Método | Ruta | Controller | Acción |
|--------|------|-----------|--------|
| GET | `/api/v1/webhooks/events` | WebhookEndpointController | availableEvents |
| GET | `/api/v1/webhooks` | WebhookEndpointController | index |
| POST | `/api/v1/webhooks` | WebhookEndpointController | store |
| GET | `/api/v1/webhooks/{id}` | WebhookEndpointController | show |
| PUT | `/api/v1/webhooks/{id}` | WebhookEndpointController | update |
| DELETE | `/api/v1/webhooks/{id}` | WebhookEndpointController | destroy |
| POST | `/api/v1/webhooks/{id}/rotate-secret` | WebhookEndpointController | rotateSecret |
| GET | `/api/v1/webhooks/{id}/deliveries` | WebhookDeliveryController | index |

### 2.2 Inventario de Modelos → Entidades TypeORM

| # | Modelo Laravel | Tabla | Relaciones | Notas TypeORM |
|---|---------------|-------|------------|---------------|
| 1 | User | users | belongsTo Company, hasMany AccessUsers | @Entity, @ManyToOne, @OneToMany |
| 2 | AccessUsers | access_users | belongsTo User | @Entity, @ManyToOne |
| 3 | CertificateRequest | certificate_requests | belongsTo (Identity, TypeOrg, City, Company), hasMany (Files, History, Analysis) | Hub entity, @ManyToOne, @OneToMany |
| 4 | ChangeHistory | change_histories | belongsTo CertificateRequest, User | @Entity, @ManyToOne |
| 5 | Company | companies | belongsTo (Country, Identity, TypeOrg, City), hasMany (CertRequests, Settings) | @Entity, @ManyToOne, @OneToMany |
| 6 | DocumentAnalysisResult | document_analysis_results | belongsTo CertificateRequest | JSON columns, scopes → QueryBuilder |
| 7 | FileManager | file_managers | belongsTo CertificateRequest | UUID auto-gen, @BeforeInsert |
| 8 | IdentityDocument | identity_documents | hasMany (CertRequests, Companies) | Master data |
| 9 | Language | languages | — | Master data |
| 10 | TypeOrganization | type_organization | hasMany (CertRequests, Companies) | Master data |
| 11 | Cities | cities | belongsTo Department, hasMany PostalCode | @ManyToOne, @OneToMany |
| 12 | Country | countries | hasMany Departments | @OneToMany |
| 13 | Department | departments | belongsTo Country, hasMany Cities | @ManyToOne, @OneToMany |
| 14 | PostalCode | postal_codes | belongsTo City | @ManyToOne |
| 15 | GeneralSetting | general_settings | hasMany GeneralSettingCompany | @OneToMany |
| 16 | GeneralSettingCompany | general_setting_companies | belongsTo (GeneralSetting, Company) | @ManyToOne join table |
| 17 | ReportHeader | reports_header | — | Config entity |
| 18 | UserType | user_types | hasMany Users | @OneToMany |
| 19 | PasswordReset | password_resets | — | Token entity |
| 20 | WebhookEndpoint | webhook_endpoints | belongsTo Company, hasMany Deliveries | Soft delete, JSON events |
| 21 | WebhookDelivery | webhook_deliveries | belongsTo WebhookEndpoint | Enum status |
| 22 | PersonalAccessToken | personal_access_tokens | belongsTo User | token SHA-256 hashed, select:false |
| 23 | Notification | notifications | polymorphic (notifiableType/Id) | UUID PK, data JSONB |

### 2.3 Patrones Arquitectónicos Actuales

```
Laravel Actual:
┌─────────────┐     ┌──────────┐     ┌───────────┐     ┌──────────┐
│  Controller  │ ──▶ │  Service  │ ──▶ │  Command  │ ──▶ │ Handler  │
└─────────────┘     └──────────┘     └───────────┘     └──────────┘
                                                             │
                                          ┌──────────────────┤
                                          ▼                  ▼
                                     ┌─────────┐      ┌──────────┐
                                     │  Model   │      │  Event   │
                                     └─────────┘      └──────────┘
```

---

## 3. ARQUITECTURA DESTINO (Clean Architecture) <a name="arquitectura-destino"></a>

### 3.1 Estructura de Carpetas

```
backnest/
├── src/
│   ├── main.ts                          # Bootstrap Fastify + NestJS
│   ├── app.module.ts                    # Root module
│   │
│   ├── common/                          # ── CAPA COMPARTIDA ──
│   │   ├── decorators/                  # Custom decorators (@CurrentUser, @ApiPaginated)
│   │   ├── dto/                         # DTOs base (PaginationQueryDto, BaseResponseDto)
│   │   ├── enums/                       # Enums compartidos
│   │   ├── exceptions/                  # Excepciones de dominio
│   │   │   ├── certificate.exception.ts
│   │   │   ├── invalid-file.exception.ts
│   │   │   └── domain-exception.filter.ts
│   │   ├── filters/                     # Exception filters globales
│   │   ├── guards/                      # Auth guards (JwtGuard, ThrottleGuard)
│   │   ├── interceptors/               # Response transform, logging, timeout
│   │   │   ├── laravel-response.interceptor.ts  # ⭐ Transforma respuestas al formato Laravel
│   │   │   ├── laravel-pagination.interceptor.ts # ⭐ Paginación estilo Laravel
│   │   │   └── logging.interceptor.ts
│   │   ├── interfaces/                  # Interfaces/contratos compartidos
│   │   ├── middleware/                  # Middleware Fastify
│   │   ├── pipes/                       # Validation pipes
│   │   ├── types/                       # TypeScript types
│   │   └── utils/                       # Utilidades (date formatter, sanitizer)
│   │
│   ├── config/                          # ── CONFIGURACIÓN ──
│   │   ├── app.config.ts
│   │   ├── auth.config.ts
│   │   ├── certificate.config.ts
│   │   ├── ai.config.ts
│   │   ├── database.config.ts
│   │   ├── mail.config.ts
│   │   ├── queue.config.ts
│   │   ├── tokens.config.ts
│   │   ├── webhooks.config.ts
│   │   └── cors.config.ts
│   │
│   ├── database/                        # ── CAPA PERSISTENCIA ──
│   │   ├── entities/                    # TypeORM entities (21)
│   │   ├── migrations/                  # TypeORM migrations
│   │   ├── seeders/                     # Data seeders
│   │   └── subscribers/                 # Entity subscribers
│   │
│   ├── modules/                         # ── MÓDULOS DE DOMINIO ──
│   │   │
│   │   ├── auth/                        # Módulo Autenticación
│   │   │   ├── auth.module.ts
│   │   │   ├── controllers/
│   │   │   │   ├── auth.controller.ts
│   │   │   │   └── register.controller.ts
│   │   │   ├── dto/
│   │   │   │   ├── login.dto.ts
│   │   │   │   ├── register.dto.ts
│   │   │   │   ├── forgot-password.dto.ts
│   │   │   │   └── reset-password.dto.ts
│   │   │   ├── guards/
│   │   │   │   └── jwt-auth.guard.ts
│   │   │   ├── strategies/
│   │   │   │   └── jwt.strategy.ts
│   │   │   ├── services/
│   │   │   │   └── auth.service.ts
│   │   │   └── interfaces/
│   │   │       └── auth-payload.interface.ts
│   │   │
│   │   ├── users/                       # Módulo Usuarios
│   │   │   ├── users.module.ts
│   │   │   ├── controllers/
│   │   │   │   └── profile.controller.ts
│   │   │   ├── dto/
│   │   │   ├── services/
│   │   │   │   └── users.service.ts
│   │   │   └── repositories/
│   │   │       └── users.repository.ts
│   │   │
│   │   ├── companies/                   # Módulo Empresas
│   │   │   ├── companies.module.ts
│   │   │   ├── controllers/
│   │   │   │   └── company.controller.ts
│   │   │   ├── dto/
│   │   │   ├── services/
│   │   │   │   ├── company.service.ts
│   │   │   │   └── general-settings.service.ts
│   │   │   └── repositories/
│   │   │       └── company.repository.ts
│   │   │
│   │   ├── certificates/               # Módulo Core Certificados
│   │   │   ├── certificates.module.ts
│   │   │   ├── controllers/
│   │   │   │   ├── certificate-request.controller.ts
│   │   │   │   └── certificate-files.controller.ts
│   │   │   ├── commands/               # Command Pattern (CQRS)
│   │   │   │   ├── create-certificate-request.command.ts
│   │   │   │   ├── update-certificate-request.command.ts
│   │   │   │   └── update-certificate-status.command.ts
│   │   │   ├── handlers/
│   │   │   │   ├── create-certificate-request.handler.ts
│   │   │   │   ├── update-certificate-request.handler.ts
│   │   │   │   └── update-certificate-status.handler.ts
│   │   │   ├── dto/
│   │   │   │   ├── create-certificate-request.dto.ts
│   │   │   │   ├── update-certificate-request.dto.ts
│   │   │   │   ├── update-certificate-status.dto.ts
│   │   │   │   └── certificate-query.dto.ts
│   │   │   ├── services/
│   │   │   │   ├── certificate-request.service.ts
│   │   │   │   ├── certificate-files.service.ts
│   │   │   │   ├── certificate-mail.service.ts
│   │   │   │   ├── certificate-processing.service.ts
│   │   │   │   └── certificate-validator.service.ts
│   │   │   ├── events/
│   │   │   │   ├── certificate-request-created.event.ts
│   │   │   │   ├── certificate-status-changed.event.ts
│   │   │   │   ├── certificate-file-uploaded.event.ts
│   │   │   │   └── certificate-request-deleted.event.ts
│   │   │   └── repositories/
│   │   │       ├── certificate-request.repository.ts
│   │   │       ├── certificate-files.repository.ts
│   │   │       └── change-history.repository.ts
│   │   │
│   │   ├── locations/                   # Módulo Localización
│   │   │   ├── locations.module.ts
│   │   │   ├── controllers/
│   │   │   │   └── location.controller.ts
│   │   │   ├── services/
│   │   │   │   └── location.service.ts
│   │   │   └── repositories/
│   │   │       └── location.repository.ts
│   │   │
│   │   ├── master/                      # Módulo Datos Maestros
│   │   │   ├── master.module.ts
│   │   │   ├── controllers/
│   │   │   │   └── master.controller.ts
│   │   │   └── services/
│   │   │       └── referenced-tables.service.ts
│   │   │
│   │   ├── consume/                     # Módulo Consumo
│   │   │   ├── consume.module.ts
│   │   │   ├── controllers/
│   │   │   │   └── consume.controller.ts
│   │   │   └── services/
│   │   │       └── consume.service.ts
│   │   │
│   │   ├── crud/                        # Módulo CRUD Genérico
│   │   │   ├── crud.module.ts
│   │   │   ├── controllers/
│   │   │   │   └── table-crud.controller.ts
│   │   │   └── services/
│   │   │       ├── table-crud.service.ts
│   │   │       └── table-validation.service.ts
│   │   │
│   │   ├── notifications/              # Módulo Notificaciones
│   │   │   ├── notifications.module.ts
│   │   │   ├── controllers/
│   │   │   │   └── notification.controller.ts
│   │   │   ├── services/
│   │   │   │   └── notification.service.ts
│   │   │   ├── templates/              # Email templates
│   │   │   └── jobs/
│   │   │       ├── send-expiring-notifications.job.ts
│   │   │       ├── send-admin-report.job.ts
│   │   │       ├── send-monthly-admin-report.job.ts
│   │   │       └── send-monthly-company-report.job.ts
│   │   │
│   │   ├── tokens/                     # Módulo PAT
│   │   │   ├── tokens.module.ts
│   │   │   ├── controllers/
│   │   │   │   └── token.controller.ts
│   │   │   ├── dto/
│   │   │   │   └── create-token.dto.ts
│   │   │   ├── guards/
│   │   │   │   └── pat-auth.guard.ts
│   │   │   ├── services/
│   │   │   │   └── token.service.ts
│   │   │   └── repositories/
│   │   │       └── token.repository.ts
│   │   │
│   │   ├── webhooks/                   # Módulo Webhooks
│   │   │   ├── webhooks.module.ts
│   │   │   ├── controllers/
│   │   │   │   ├── webhook-endpoint.controller.ts
│   │   │   │   └── webhook-delivery.controller.ts
│   │   │   ├── dto/
│   │   │   ├── services/
│   │   │   │   ├── webhook-dispatcher.service.ts
│   │   │   │   └── webhook-signer.service.ts
│   │   │   ├── builders/               # Payload builders por evento
│   │   │   ├── jobs/
│   │   │   │   └── deliver-webhook.job.ts
│   │   │   ├── listeners/
│   │   │   └── repositories/
│   │   │       ├── webhook-endpoint.repository.ts
│   │   │       └── webhook-delivery.repository.ts
│   │   │
│   │   ├── ai/                         # Módulo IA/OCR
│   │   │   ├── ai.module.ts
│   │   │   ├── services/
│   │   │   │   ├── unified-ocr.service.ts
│   │   │   │   ├── aws-textract.service.ts
│   │   │   │   ├── google-vision.service.ts
│   │   │   │   ├── ai-content.service.ts
│   │   │   │   └── document-analysis.service.ts
│   │   │   ├── jobs/
│   │   │   │   └── process-certificate.job.ts
│   │   │   └── repositories/
│   │   │       └── document-analysis.repository.ts
│   │   │
│   │   ├── mail/                       # Módulo Email
│   │   │   ├── mail.module.ts
│   │   │   ├── services/
│   │   │   │   └── mail.service.ts
│   │   │   └── templates/
│   │   │       ├── register.hbs
│   │   │       ├── certificate-expiring.hbs
│   │   │       └── password-reset.hbs
│   │   │
│   │   └── reports/                    # Módulo Reportes
│   │       ├── reports.module.ts
│   │       ├── controllers/
│   │       │   └── reports-header.controller.ts
│   │       └── services/
│   │           └── reports-header.service.ts
│   │
│   └── shared/                          # ── CAPA SHARED ──
│       ├── logger/
│       │   └── smart-logger.service.ts  # Logger centralizado
│       └── health/
│           └── health.controller.ts     # Health check
│
├── test/                                # Tests
│   ├── unit/
│   ├── integration/
│   ├── e2e/
│   └── fixtures/
│
├── docs/                                # Documentación
├── docker/                              # Docker configs
├── .env.example
├── .eslintrc.js
├── .prettierrc
├── nest-cli.json
├── package.json
├── tsconfig.json
├── tsconfig.build.json
└── README.md
```

### 3.2 Diagrama de Capas (Clean Architecture)

```
┌──────────────────────────────────────────────────────────────┐
│                    CAPA DE INFRAESTRUCTURA                    │
│  ┌───────────┐ ┌──────────┐ ┌─────────┐ ┌───────────────┐   │
│  │  Fastify   │ │ TypeORM  │ │  Bull   │ │  Nodemailer   │   │
│  │  Adapter   │ │  Repos   │ │  Queue  │ │  Templates    │   │
│  └───────────┘ └──────────┘ └─────────┘ └───────────────┘   │
├──────────────────────────────────────────────────────────────┤
│                   CAPA DE PRESENTACIÓN                        │
│  ┌───────────────┐ ┌──────────┐ ┌───────┐ ┌─────────────┐   │
│  │  Controllers   │ │  Guards  │ │ Pipes │ │Interceptors │   │
│  │  (REST API)    │ │ (Auth)   │ │(Valid)│ │ (Transform) │   │
│  └───────────────┘ └──────────┘ └───────┘ └─────────────┘   │
├──────────────────────────────────────────────────────────────┤
│                    CAPA DE APLICACIÓN                         │
│  ┌───────────────┐ ┌──────────┐ ┌────────┐ ┌────────────┐   │
│  │   Services     │ │ Commands │ │Handlers│ │   DTOs     │   │
│  │ (Use Cases)    │ │ (CQRS)   │ │        │ │            │   │
│  └───────────────┘ └──────────┘ └────────┘ └────────────┘   │
├──────────────────────────────────────────────────────────────┤
│                      CAPA DE DOMINIO                          │
│  ┌───────────────┐ ┌──────────┐ ┌────────────────────────┐   │
│  │   Entities     │ │  Events  │ │   Interfaces/Contracts │   │
│  │ (TypeORM)      │ │          │ │                        │   │
│  └───────────────┘ └──────────┘ └────────────────────────┘   │
└──────────────────────────────────────────────────────────────┘
```

---

## 4. CONVENCIONES Y ESTÁNDARES <a name="convenciones-y-estándares"></a>

### 4.1 Nomenclatura

| Elemento | Convención | Ejemplo |
|---------|-----------|---------|
| Archivos | kebab-case | `certificate-request.service.ts` |
| Clases | PascalCase | `CertificateRequestService` |
| Interfaces | PascalCase con prefijo I | `ICertificateRequestRepository` |
| DTOs | PascalCase con sufijo Dto | `CreateCertificateRequestDto` |
| Entidades | PascalCase (singular) | `CertificateRequest` |
| Enums | PascalCase | `DocumentStatusEnum` |
| Variables/Métodos | camelCase | `getCertificateRequest()` |
| Constantes | UPPER_SNAKE_CASE | `MAX_FILE_SIZE` |
| Tabla BD | snake_case (plural) | `certificate_requests` |
| Columnas BD | snake_case | `request_status` |

### 4.2 Reglas de Estilo

- ESLint + Prettier configurados
- Strict TypeScript (`strict: true`)
- Barrel exports por módulo (`index.ts`)
- DTOs con `class-validator` y `class-transformer`
- Todos los servicios inyectados vía constructor
- Logger: `SmartLoggerService` (nunca `console.log`)
- Timezone: `America/Bogota` (consistente con Laravel)
- Formato de fechas: `d-m-Y h:i:s a` (replicar formato Laravel)

### 4.3 Reglas SOLID

| Principio | Aplicación |
|-----------|-----------|
| **S** - Single Responsibility | Un servicio = una responsabilidad. `CertificateRequestService` NO maneja archivos. |
| **O** - Open/Closed | Estrategia OCR extensible sin modificar `UnifiedOcrService`. |
| **L** - Liskov Substitution | `AwsTextractService` y `GoogleVisionService` implementan `IOcrService`. |
| **I** - Interface Segregation | Interfaces granulares: `ICrudRepository`, `IPaginatedRepository`. |
| **D** - Dependency Inversion | Controllers dependen de interfaces, no de implementaciones concretas. |

---

## 5. COMPATIBILIDAD DE RESPUESTAS LARAVEL <a name="compatibilidad-de-respuestas-laravel"></a>

### 5.1 Formato de Respuesta Estándar

Todas las respuestas deben seguir exactamente esta estructura:

```typescript
// Respuesta exitosa simple
{
  "success": true,
  "dataRecords": {
    "data": [ /* array de objetos */ ]
  }
}

// Respuesta exitosa con mensaje
{
  "success": true,
  "message": "Operación exitosa"
}

// Respuesta exitosa con dato único
{
  "success": true,
  "dataRecords": {
    "data": { /* objeto único */ }
  }
}

// Respuesta error
{
  "success": false,
  "message": "Descripción del error",
  "errors": { /* errores de validación */ }
}
```

### 5.2 Formato de Paginación Laravel

```typescript
// GET /api/v1/certificate-request?limit=15&page=1
{
  "success": true,
  "dataRecords": {
    "data": [ /* items */ ],
    "current_page": 1,
    "first_page_url": "http://host/api/v1/certificate-request?page=1",
    "from": 1,
    "last_page": 5,
    "last_page_url": "http://host/api/v1/certificate-request?page=5",
    "links": [
      { "url": null, "label": "&laquo; Previous", "active": false },
      { "url": "http://host/api/v1/certificate-request?page=1", "label": "1", "active": true },
      { "url": "http://host/api/v1/certificate-request?page=2", "label": "2", "active": false },
      { "url": "http://host/api/v1/certificate-request?page=2", "label": "Next &raquo;", "active": false }
    ],
    "next_page_url": "http://host/api/v1/certificate-request?page=2",
    "path": "http://host/api/v1/certificate-request",
    "per_page": 15,
    "prev_page_url": null,
    "to": 15,
    "total": 73
  }
}
```

### 5.3 Interceptor de Respuesta Laravel (Implementación Clave)

```typescript
// src/common/interceptors/laravel-pagination.interceptor.ts
// Este interceptor transforma la paginación de TypeORM al formato exacto de Laravel

interface LaravelPaginationMeta {
  current_page: number;
  first_page_url: string;
  from: number;
  last_page: number;
  last_page_url: string;
  links: Array<{ url: string | null; label: string; active: boolean }>;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number;
  total: number;
}
```

### 5.4 Formato de Error de Validación (Replica Laravel 422)

```typescript
// Error 422 - Validation
{
  "success": false,
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "email": ["El email es obligatorio.", "El email debe ser válido."],
    "password": ["La contraseña es obligatoria."]
  }
}
```

### 5.5 Códigos de Estado HTTP

| Acción | Código | Respuesta |
|--------|--------|-----------|
| GET exitoso | 200 | `{ success: true, dataRecords: { data } }` |
| POST crear recurso | 201 | `{ success: true, dataRecords: { data } }` |
| PUT/PATCH actualizar | 200 | `{ success: true, dataRecords: { data } }` |
| DELETE eliminar | 200 | `{ success: true, message }` |
| Validación fallida | 422 | `{ success: false, message, errors }` |
| No autenticado | 401 | `{ success: false, message: "Unauthenticated." }` |
| No autorizado | 403 | `{ success: false, message: "Forbidden." }` |
| No encontrado | 404 | `{ success: false, message: "Not found." }` |
| Throttle | 429 | `{ success: false, message: "Too Many Requests." }` |
| Error servidor | 500 | `{ success: false, message }` |

---

## 6. PRODUCT BACKLOG <a name="product-backlog"></a>

| ID | Épica | Historia de Usuario | Prioridad | Story Points |
|----|-------|---------------------|-----------|-------------|
| E1 | Fundación | Setup proyecto NestJS + Fastify + TypeORM + Docker | Must | 21 |
| E2 | Auth | Sistema de autenticación JWT compatible con Passport Laravel | Must | 21 |
| E3 | Empresas | CRUD de empresas + datos maestros + localización | Must | 13 |
| E4 | Certificados | CRUD solicitudes de certificados (core business) | Must | 34 |
| E5 | Archivos | Upload/download de archivos con validación MIME | Must | 13 |
| E6 | Notificaciones | Sistema de notificaciones + email templates | Must | 21 |
| E7 | Webhooks | Sistema completo de webhooks salientes | Should | 21 |
| E8 | IA/OCR | Integración AWS Textract + Google Vision + Gemini | Should | 21 |
| E9 | Reportes | Reportes PDF/Excel + tareas programadas (cron) | Should | 13 |
| E10 | PAT | Tokens de acceso personal | Should | 8 |
| E11 | QA | Testing E2E + Performance + Deployment | Must | 21 |
| **Total** | | | | **207 SP** |

---

## 7. SPRINT 0 — FUNDACIÓN (Semanas 1-2) <a name="sprint-0"></a>

> **Objetivo:** Establecer la base del proyecto con toda la infraestructura, configuración, entidades TypeORM, y los interceptors de compatibilidad Laravel.

### Sprint Backlog

| # | Tarea | SP | Responsabilidad | Criterio de Aceptación |
|---|-------|----|-----------------|----------------------|
| 0.1 | Inicializar proyecto NestJS con Fastify adapter | 2 | DevOps | `npm run start:dev` arranca sin errores |
| 0.2 | Configurar TypeORM con PostgreSQL | 2 | Backend | Conexión exitosa a BD, migrations ejecutan |
| 0.3 | Configurar módulo de configuración (@nestjs/config) | 1 | Backend | Variables de entorno cargadas por namespace |
| 0.4 | Crear 23 entidades TypeORM con relaciones | 5 | Backend | Esquema sincroniza con BD existente Laravel |
| 0.5 | Crear interceptor `LaravelResponseInterceptor` | 3 | Backend | Respuestas siguen formato `{ success, dataRecords }` |
| 0.6 | Crear interceptor `LaravelPaginationInterceptor` | 3 | Backend | Paginación idéntica a Laravel (links, urls, meta) |
| 0.7 | Crear exception filters globales | 2 | Backend | Errores 422/401/403/404/500 formato Laravel |
| 0.8 | Crear `SmartLoggerService` | 1 | Backend | Logger funcional sin datos sensibles |
| 0.9 | Configurar Docker (Dockerfile + compose.yaml) | 1 | DevOps | `docker compose up` levanta app + BD |
| 0.10 | Configurar ESLint + Prettier + Husky | 1 | DevOps | Lint pasa sin errores |
| **Total** | | **21** | | |

### Entregables Sprint 0 ✅ COMPLETADO

- [x] Proyecto NestJS booteable con Fastify
- [x] 23 entidades TypeORM mapeadas a las tablas existentes de Laravel
- [x] Interceptor de respuestas Laravel (`LaravelResponseInterceptor`)
- [x] Interceptor de paginación Laravel (`LaravelPaginationInterceptor`) — señal `__paginated: true`
- [x] Exception filter global (`LaravelExceptionFilter`) — replica formato 422/401/403/404/500
- [x] SmartLoggerService (`@Global()`, suprime debug en producción)
- [x] Docker ready (`Dockerfile` multi-stage, `docker-compose.yml`, `docker-compose.dev.yml`)
- [x] Linting + formatting configurado (ESLint + Prettier)
- [x] Archivo `.env.example` con todas las variables necesarias
- [x] `typeorm.config.ts` para CLI de migraciones
- [x] `src/database/entities/index.ts` barrel export de las 23 entidades
- [x] `README.md` con guía de instalación y comandos

### Detalles Técnicos

#### 0.1 Bootstrap Fastify

```typescript
// src/main.ts
import { NestFactory } from '@nestjs/core';
import { FastifyAdapter, NestFastifyApplication } from '@nestjs/platform-fastify';
import { AppModule } from './app.module';
import { ValidationPipe } from '@nestjs/common';
import fastifyMultipart from '@fastify/multipart';

async function bootstrap() {
  const app = await NestFactory.create<NestFastifyApplication>(
    AppModule,
    new FastifyAdapter(),
  );

  app.setGlobalPrefix('api');

  await app.register(fastifyMultipart, {
    limits: { fileSize: 2 * 1024 * 1024 }, // 2MB como en Laravel
  });

  app.useGlobalPipes(new ValidationPipe({
    whitelist: true,
    transform: true,
    forbidNonWhitelisted: true,
  }));

  app.enableCors({ origin: '*', methods: '*' });

  await app.listen(3000, '0.0.0.0');
}
bootstrap();
```

#### 0.4 Ejemplo Entidad TypeORM (CertificateRequest)

```typescript
// src/database/entities/certificate-request.entity.ts
@Entity('certificate_requests')
export class CertificateRequest {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ type: 'uuid', unique: true })
  uuid: string;

  @Column({ name: 'company_id' })
  companyId: number;

  @Column({ name: 'city_id' })
  cityId: number;

  @Column({ name: 'identity_document_id' })
  identityDocumentId: number;

  @Column({ name: 'type_organization_id' })
  typeOrganizationId: number;

  @Column({ name: 'document_number', length: 30 })
  documentNumber: string;

  @Column({ length: 255 })
  address: string;

  @Column({ name: 'legal_representative', length: 120 })
  legalRepresentative: string;

  @Column({ name: 'company_name', length: 120 })
  companyName: string;

  @Column({ length: 30 })
  dni: string;

  @Column({ nullable: true })
  dv: number;

  @Column({ type: 'int' })
  life: number;

  @Column({ type: 'text', nullable: true })
  info: string;

  @Column({ name: 'request_status', length: 20, default: 'DRAFT' })
  requestStatus: string;

  @Column({ name: 'expiration_date', type: 'timestamp', nullable: true })
  expirationDate: Date;

  @CreateDateColumn({ name: 'created_at' })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at' })
  updatedAt: Date;

  // Relaciones
  @ManyToOne(() => Company, (company) => company.certificateRequests)
  @JoinColumn({ name: 'company_id' })
  company: Company;

  @ManyToOne(() => IdentityDocument)
  @JoinColumn({ name: 'identity_document_id' })
  identityDocument: IdentityDocument;

  @ManyToOne(() => TypeOrganization)
  @JoinColumn({ name: 'type_organization_id' })
  typeOrganization: TypeOrganization;

  @ManyToOne(() => City)
  @JoinColumn({ name: 'city_id' })
  city: City;

  @OneToMany(() => FileManager, (file) => file.certificateRequest)
  files: FileManager[];

  @OneToMany(() => ChangeHistory, (history) => history.certificateRequest)
  changeHistories: ChangeHistory[];

  @OneToMany(() => DocumentAnalysisResult, (analysis) => analysis.certificateRequest)
  analysisResults: DocumentAnalysisResult[];
}
```

#### 0.5 Interceptor Laravel Response

```typescript
// src/common/interceptors/laravel-response.interceptor.ts
@Injectable()
export class LaravelResponseInterceptor implements NestInterceptor {
  intercept(context: ExecutionContext, next: CallHandler): Observable<any> {
    return next.handle().pipe(
      map((data) => {
        if (data?.isLaravelFormatted) return data;

        return {
          success: true,
          dataRecords: {
            data: data?.data ?? data,
          },
          ...(data?.message && { message: data.message }),
        };
      }),
    );
  }
}
```

---

## 8. SPRINT 1 — AUTENTICACIÓN Y USUARIOS (Semanas 3-4) <a name="sprint-1"></a>

> **Objetivo:** Migrar el sistema completo de autenticación OAuth2/JWT, registro de usuarios, verificación de email, y reset de contraseña.

### Sprint Backlog

| # | Tarea | SP | Criterio de Aceptación |
|---|-------|----|----------------------|
| 1.1 | Implementar `AuthModule` con JWT Strategy | 3 | Login retorna token Bearer idéntico al de Laravel Passport |
| 1.2 | Implementar `POST /api/v1/auth/login` | 2 | Misma validación y respuesta que Laravel |
| 1.3 | Implementar `GET /api/v1/auth/logout` | 1 | Token revocado, 200 OK |
| 1.4 | Implementar `GET /api/v1/auth/user` | 1 | Retorna perfil usuario autenticado |
| 1.5 | Implementar `POST /api/v1/register` | 3 | Crea empresa + usuario admin en transacción |
| 1.6 | Implementar `POST /api/v1/forgot-password` | 2 | Envía email reset password |
| 1.7 | Implementar `POST /api/v1/reset-password` | 2 | Restablece contraseña con token |
| 1.8 | Implementar `GET /api/v1/verify-email/{id}/{hash}` | 2 | Verifica email con URL firmada |
| 1.9 | Implementar `POST /api/v1/email/verification-notification` | 1 | Reenvía email verificación |
| 1.10 | Implementar `JwtAuthGuard` global | 1 | Protege rutas `auth:api` |
| 1.11 | Implementar `ThrottleGuard` (rate limiting) | 2 | Rate limiting compatible con middleware Laravel |
| 1.12 | Tests unitarios AuthService | 1 | Cobertura ≥ 80% |
| **Total** | | **21** | |

### Endpoints Migrados

```
POST   /api/v1/auth/login                        ✅
GET    /api/v1/auth/logout                        ✅
GET    /api/v1/auth/user                          ✅
POST   /api/v1/register                           ✅
POST   /api/v1/forgot-password                    ✅
POST   /api/v1/reset-password                     ✅
GET    /api/v1/verify-email/{id}/{hash}           ✅
POST   /api/v1/email/verification-notification    ✅
```

### Notas de Implementación

- **JWT vs Passport OAuth2:** Laravel usa Passport (OAuth2 con tokens opaque). Para NestJS replicar el formato de token Bearer exacto. Si el frontend envía `Authorization: Bearer {token}`, el JWT debe validar igual.
- **Signed URLs:** Para verificación de email, usar `@nestjs/jwt` para generar URLs firmadas con HMAC-SHA256 idénticas a `URL::signedRoute()` de Laravel.
- **Transacción de registro:** `RegisteredUserController.store()` usa `DB::beginTransaction()`. En TypeORM usar `queryRunner.startTransaction()`.
- **Throttle:** Replicar las mismas ventanas de rate limiting:
  - `throttle:6,1` = 6 requests por minuto
  - `throttle:certificate-create` = configuración custom
  - `throttle:send-mail` = configuración custom

---

## 9. SPRINT 2 — MÓDULO DE EMPRESAS Y DATOS MAESTROS (Semanas 5-6) <a name="sprint-2"></a>

> **Objetivo:** Migrar módulos de empresa, localización y datos maestros (tablas de referencia).

### Sprint Backlog

| # | Tarea | SP | Criterio de Aceptación |
|---|-------|----|----------------------|
| 2.1 | Implementar `LocationModule` con servicios | 2 | 3 endpoints públicos respondiendo idéntico |
| 2.2 | Implementar `GET /api/v1/countries` | 1 | Respuesta idéntica a Laravel |
| 2.3 | Implementar `GET /api/v1/departments` | 1 | Respuesta idéntica a Laravel |
| 2.4 | Implementar `GET /api/v1/cities` con filtros | 1 | Filtros `query` y `code` funcionando |
| 2.5 | Implementar `MasterModule` | 1 | Datos maestros consultables |
| 2.6 | Implementar `GET /api/v1/identity-documents` | 1 | Respuesta idéntica |
| 2.7 | Implementar `GET /api/v1/organization-type` | 1 | Respuesta idéntica |
| 2.8 | Implementar `CompaniesModule` completo | 2 | CRUD empresa funcional |
| 2.9 | Implementar `GET /api/v1/company` | 1 | Datos empresa del usuario autenticado |
| 2.10 | Implementar `GET /api/v1/company/settings` | 1 | Configuración empresa |
| 2.11 | Implementar `PUT /api/v1/company/settings` | 1 | Actualiza settings joined con general_settings |
| 2.12 | Tests unitarios módulos Location, Master, Company | 1 | Cobertura ≥ 80% |
| **Total** | | **13** (Sprint más ligero) | |

### Endpoints Migrados

```
GET    /api/v1/countries                          ✅
GET    /api/v1/departments                        ✅
GET    /api/v1/cities                             ✅
GET    /api/v1/identity-documents                 ✅
GET    /api/v1/organization-type                  ✅
GET    /api/v1/company                            ✅
GET    /api/v1/company/settings                   ✅
PUT    /api/v1/company/settings                   ✅
```

### Notas de Implementación

- **Multi-tenancy:** Todas las queries de `CompanyController` filtran por `company_id` del usuario autenticado. Implementar decorator `@CurrentCompany()` para inyectar automáticamente.
- **GeneralSettingCompany:** Relación compleja join entre `general_settings` y `general_setting_companies`. En TypeORM usar `createQueryBuilder` con `leftJoinAndSelect`.

---

## 10. SPRINT 3 — MÓDULO DE SOLICITUDES DE CERTIFICADOS (Semanas 7-8) <a name="sprint-3"></a>

> **Objetivo:** Migrar el core business — CRUD completo de solicitudes de certificados con Command Pattern, paginación, filtros, y gestión de estado.

### Sprint Backlog

| # | Tarea | SP | Criterio de Aceptación |
|---|-------|----|----------------------|
| 3.1 | Implementar `CertificatesModule` con DI completa | 2 | Módulo registrado y funcional |
| 3.2 | Implementar Command + Handlers (CQRS) | 5 | CreateCommand, UpdateCommand, UpdateStatusCommand con handlers |
| 3.3 | Implementar `POST /api/v1/certificate-request` | 3 | Crear solicitud con validación, multipart, middleware throttle + MIME |
| 3.4 | Implementar `GET /api/v1/certificate-request` con paginación | 3 | Paginación Laravel exacta, filtros: status, query, dates, limit |
| 3.5 | Implementar `GET /api/v1/certificate-request/all` | 2 | Admin: listar todas con paginación |
| 3.6 | Implementar `GET /api/v1/certificate-request/{id}` | 1 | Detalle con relaciones eager loaded |
| 3.7 | Implementar `PUT /api/v1/certificate-request/{id}` | 2 | Actualizar solicitud |
| 3.8 | Implementar `PUT /api/v1/certificate-request/{id}/status` | 3 | Cambio estado + ChangeHistory + Evento |
| 3.9 | Implementar `DELETE /api/v1/certificate-request/{id}` | 1 | Soft delete + evento |
| 3.10 | Implementar `POST /api/v1/certificate-request/{id}/send-mail` | 2 | Envío email con throttle |
| 3.11 | Implementar eventos de dominio (EventEmitter2) | 3 | 4 eventos emitidos correctamente |
| 3.12 | Implementar `CertificateRequestRepository` custom | 3 | Queries optimizadas con eager loading |
| 3.13 | Implementar `ChangeHistoryRepository` | 1 | Auditoría de cambios de estado |
| 3.14 | Tests unitarios: Services, Handlers, DTOs | 3 | Cobertura ≥ 80% |
| **Total** | | **34** (Sprint más pesado, core del sistema) | |

### Endpoints Migrados

```
POST   /api/v1/certificate-request                        ✅
GET    /api/v1/certificate-request                        ✅
GET    /api/v1/certificate-request/all                    ✅
GET    /api/v1/certificate-request/{id}                   ✅
PUT    /api/v1/certificate-request/{id}                   ✅
PUT    /api/v1/certificate-request/{id}/status            ✅
DELETE /api/v1/certificate-request/{id}                   ✅
POST   /api/v1/certificate-request/{id}/send-mail         ✅
```

### Notas de Implementación

- **Command Pattern CQRS:** Replicar exactamente el patrón `Service → Command → Handler` de Laravel.
- **Paginación:** El parámetro es `limit` (no `per_page`), default 15. Transformar con el interceptor a formato Laravel completo incluyendo `links[]`, `first_page_url`, etc.
- **Filtros:** `request_status`, `query` (búsqueda en nombre/dni), `start_date`, `end_date`.
- **Eventos:** Usar `@nestjs/event-emitter` (`EventEmitter2`):
  - `CertificateRequestCreated`
  - `CertificateStatusChanged`
  - `CertificateRequestDeleted`
  - `CertificateFileUploaded`
- **Sanitización:** Replicar `strip_tags()`, `Str::upper()` de Laravel.
- **Estados válidos:** `DRAFT`, `SENT`, `PENDING`, `ACCEPTED`, `PROCESSING`, `PROCESSED`, `REJECTED`.

---

## 11. SPRINT 4 — GESTIÓN DE ARCHIVOS Y STORAGE (Semanas 9-10) <a name="sprint-4"></a>

> **Objetivo:** Migrar la gestión de archivos (upload/download/delete) con validación MIME real, extracción ZIP/P12, y storage S3-compatible.

### Sprint Backlog

| # | Tarea | SP | Criterio de Aceptación |
|---|-------|----|----------------------|
| 4.1 | Implementar `POST /api/v1/certificate-request/{id}/files` | 3 | Upload multipart con validación MIME real (magic bytes) |
| 4.2 | Implementar `DELETE /api/v1/certificate-request/{id}/files/{fileId}` | 1 | Elimina archivo de storage + BD |
| 4.3 | Implementar `ValidateMimeType` Pipe/Guard | 2 | Valida contenido real del archivo (no extensión) |
| 4.4 | Implementar `ZipExtractorService` | 2 | Extrae ZIP con contraseña, busca P12/PFX |
| 4.5 | Implementar `CertificateValidatorService` | 2 | Parsea PKCS#12, extrae fecha de vencimiento |
| 4.6 | Implementar storage abstracto (local/S3) | 2 | Funciona con disco local y AWS S3 |
| 4.7 | Tests unitarios file services | 1 | Cobertura ≥ 80% |
| **Total** | | **13** | |

### Endpoints Migrados

```
POST   /api/v1/certificate-request/{id}/files             ✅
DELETE /api/v1/certificate-request/{id}/files/{fileId}    ✅
```

### Notas de Implementación

- **MIME Validation:** En Laravel se usa `finfo_file()`. En NestJS usar `file-type` npm package para detectar por magic bytes.
- **Límites:** Máx 6 archivos por solicitud, 2MB por archivo, formatos: PDF/JPG/PNG/ZIP.
- **ZIP con P12:** Cuando se sube un ZIP, extraer, buscar `.p12`/`.pfx`, validar con OpenSSL, extraer `expiration_date`.
- **UUID:** `FileManager` genera UUID automáticamente (`@BeforeInsert`).
- **Storage path:** `companies/{companyId}/{year}/{month}/{dni}/{filename}`.

---

## 12. SPRINT 5 — NOTIFICACIONES Y EMAIL (Semanas 11-12) <a name="sprint-5"></a>

> **Objetivo:** Migrar el sistema de notificaciones (15 clases), email templates, y los endpoints de gestión de notificaciones.

### Sprint Backlog

| # | Tarea | SP | Criterio de Aceptación |
|---|-------|----|----------------------|
| 5.1 | Configurar `@nestjs-modules/mailer` con Handlebars | 2 | Envío de emails funcional |
| 5.2 | Migrar templates de email (Blade → Handlebars) | 3 | Emails renderizados idénticos |
| 5.3 | Implementar `MailModule` con `MailService` | 2 | Envío con adjuntos, headers custom (X-DNI, X-SES) |
| 5.4 | Implementar `GET /api/v1/certificates/expiring` | 2 | Lista certificados próximos a vencer con urgency levels |
| 5.5 | Implementar `GET /api/v1/notifications` | 2 | Lista notificaciones paginadas del usuario |
| 5.6 | Implementar `POST /api/v1/notifications/{id}/read` | 1 | Marca como leída |
| 5.7 | Implementar `POST /api/v1/notifications/read-all` | 1 | Marca todas como leídas |
| 5.8 | Implementar `POST /api/v1/admin/certificates/notify-now` | 2 | Trigger manual de notificaciones (admin) |
| 5.9 | Implementar 15 clases de notificación | 3 | Cada notificación envía email correcto |
| 5.10 | Implementar listeners de eventos para notificaciones | 2 | Eventos disparan notificaciones automáticamente |
| 5.11 | Tests unitarios NotificationService, MailService | 1 | Cobertura ≥ 80% |
| **Total** | | **21** | |

### Endpoints Migrados

```
GET    /api/v1/certificates/expiring                      ✅
GET    /api/v1/notifications                              ✅
POST   /api/v1/notifications/{id}/read                    ✅
POST   /api/v1/notifications/read-all                     ✅
POST   /api/v1/admin/certificates/notify-now              ✅
```

### Notas de Implementación

- **Urgency Levels:** Replicar exactamente: critical (1-7 días), high (8-15), medium (16-30).
- **Cache de notificaciones:** TTL 24h para evitar duplicados (usar `@nestjs/cache-manager` con Redis o en memoria).
- **Headers email:** `X-DNI-COMPANY`, `X-SES-CONFIGURATION-SET`.
- **Templates:** Migrar de Blade a Handlebars (`.hbs`). Mantener el mismo diseño visual.
- **Notificaciones DB:** Usar tabla `notifications` con esquema polymorphic de Laravel (type, notifiable_type, notifiable_id, data JSON).

---

## 13. SPRINT 6 — WEBHOOKS Y EVENTOS (Semanas 13-14) <a name="sprint-6"></a>

> **Objetivo:** Migrar el módulo completo de webhooks salientes con firma HMAC-SHA256, reintentos, y payload builders.

### Sprint Backlog

| # | Tarea | SP | Criterio de Aceptación |
|---|-------|----|----------------------|
| 6.1 | Implementar `WebhooksModule` completo | 2 | Módulo registrado con DI |
| 6.2 | Implementar CRUD webhooks (5 endpoints) | 3 | index, store, show, update, destroy |
| 6.3 | Implementar `GET /api/v1/webhooks/events` | 1 | Lista eventos disponibles |
| 6.4 | Implementar `POST /api/v1/webhooks/{id}/rotate-secret` | 1 | Genera nuevo secret HMAC |
| 6.5 | Implementar `GET /api/v1/webhooks/{id}/deliveries` | 2 | Log de entregas paginado |
| 6.6 | Implementar `WebhookDispatcherService` | 3 | HTTP POST con firma HMAC-SHA256 |
| 6.7 | Implementar `WebhookSignerService` | 1 | HMAC-SHA256 idéntico al de Laravel |
| 6.8 | Implementar 5 PayloadBuilders | 2 | Builders por tipo de evento |
| 6.9 | Implementar `DeliverWebhookJob` (Bull Queue) | 3 | Entrega async con reintentos (5s, 5m, 1h) |
| 6.10 | Implementar listeners que despachan webhooks | 2 | Cada evento de dominio → webhook delivery |
| 6.11 | Tests unitarios webhook services | 1 | Cobertura ≥ 80% |
| **Total** | | **21** | |

### Endpoints Migrados

```
GET    /api/v1/webhooks/events                            ✅
GET    /api/v1/webhooks                                   ✅
POST   /api/v1/webhooks                                   ✅
GET    /api/v1/webhooks/{id}                              ✅
PUT    /api/v1/webhooks/{id}                              ✅
DELETE /api/v1/webhooks/{id}                              ✅
POST   /api/v1/webhooks/{id}/rotate-secret                ✅
GET    /api/v1/webhooks/{id}/deliveries                   ✅
```

### Notas de Implementación

- **Bull Queue:** Usar `@nestjs/bull` para jobs de entrega. Reintentos con backoff exponencial.
- **HMAC-SHA256:** Firma: `crypto.createHmac('sha256', secret).update(JSON.stringify(payload)).digest('hex')`.
- **Max failures:** Después de 10 fallos consecutivos, desactivar endpoint automáticamente.
- **Limpieza:** Job programado para eliminar deliveries > 30 días.
- **Límite:** Máximo 5 endpoints por empresa.

---

## 14. SPRINT 7 — IA/OCR Y PROCESAMIENTO ASÍNCRONO (Semanas 15-16) <a name="sprint-7"></a>

> **Objetivo:** Migrar la integración de IA (AWS Textract, Google Vision, Google Gemini) y el procesamiento asíncrono de certificados.

### Sprint Backlog

| # | Tarea | SP | Criterio de Aceptación |
|---|-------|----|----------------------|
| 7.1 | Implementar `AiModule` | 1 | Módulo con inyección condicional de servicios |
| 7.2 | Implementar `AwsTextractService` | 3 | Extracción de texto de imágenes/PDFs vía Textract |
| 7.3 | Implementar `GoogleVisionService` | 3 | OCR vía Google Cloud Vision |
| 7.4 | Implementar `UnifiedOcrService` (Strategy Pattern) | 2 | Selección automática + fallback |
| 7.5 | Implementar `AiContentService` (Google Gemini) | 3 | Análisis inteligente de texto extraído |
| 7.6 | Implementar `DocumentAnalysisService` | 2 | Validación cruzada de documentos |
| 7.7 | Implementar `ProcessCertificateJob` (Bull Queue) | 3 | OCR → IA → Clasificación → Evento |
| 7.8 | Implementar `CertificateProcessingService` | 2 | Orquestador: process, batch, reprocessFailed |
| 7.9 | Implementar `HandleCertificateAIProcessing` Listener | 1 | Auto-popula datos desde resultados IA |
| 7.10 | Tests unitarios IA services (con mocks) | 1 | Cobertura ≥ 80% |
| **Total** | | **21** | |

### Notas de Implementación

- **Strategy Pattern:** `UnifiedOcrService` inyecta `AwsTextractService` o `GoogleVisionService` según config `ai.ocr_service`.
- **AWS SDK v3:** Usar `@aws-sdk/client-textract` (no v2).
- **Gemini:** Usar `@google/generative-ai` npm package.
- **Timeout:** 30s para OCR, 5min para job completo.
- **Reintentos:** 3 intentos con backoff [60s, 120s, 300s].
- **Modo batch:** Procesar múltiples archivos de una solicitud secuencialmente.

---

## 15. SPRINT 8 — REPORTES, EXPORTS Y TAREAS PROGRAMADAS (Semanas 17-18) <a name="sprint-8"></a>

> **Objetivo:** Migrar reportes PDF/Excel, CRUD genérico, consumo, configuración de reportes, y todas las tareas programadas (cron jobs).

### Sprint Backlog

| # | Tarea | SP | Criterio de Aceptación |
|---|-------|----|----------------------|
| 8.1 | Implementar `CrudModule` (CRUD genérico) | 2 | 5 endpoints CRUD funcionando |
| 8.2 | Implementar `ConsumeModule` | 1 | Vista de consumo por año/mes |
| 8.3 | Implementar `GET/PUT /api/v1/settings/reports` | 1 | Configuración de encabezados |
| 8.4 | Implementar `GET/PUT /api/v1/profile` + `GET /api/v1/profile/types` | 1 | Perfil usuario |
| 8.5 | Implementar tareas programadas con `@nestjs/schedule` | 3 | 5 cron jobs funcionando en horarios correctos |
| 8.6 | Implementar `SendExpiringNotificationsJob` (diario 08:00) | 2 | Notificaciones enviadas con cache anti-duplicado |
| 8.7 | Implementar `SendAdminReportJob` (diario 07:00 + semanal lunes 09:00) | 1 | Reportes admin con stats |
| 8.8 | Implementar `SendMonthlyAdminReportJob` (último día 23:00) | 1 | Informe mensual consolidado |
| 8.9 | Implementar `SendMonthlyCompanyReportJob` (último día 22:00) | 1 | Informe por empresa |
| **Total** | | **13** | |

### Endpoints Migrados

```
GET    /api/v1/crud                                       ✅
POST   /api/v1/crud                                       ✅
GET    /api/v1/crud/{id}                                  ✅
PUT    /api/v1/crud/{id}                                  ✅
DELETE /api/v1/crud/{id}                                  ✅
GET    /api/v1/consume/{year}                             ✅
GET    /api/v1/consume/{year}/{month}                     ✅
GET    /api/v1/settings/reports                           ✅
PUT    /api/v1/settings/reports/{id}                      ✅
GET    /api/v1/profile                                    ✅
GET    /api/v1/profile/types                              ✅
PUT    /api/v1/profile/{id}                               ✅
```

### Notas de Implementación

- **Cron Jobs:** Usar `@nestjs/schedule` con decoradores `@Cron()`:
  ```typescript
  @Cron('0 8 * * *', { timeZone: 'America/Bogota' })  // Diario 08:00
  @Cron('0 7 * * *', { timeZone: 'America/Bogota' })  // Diario 07:00
  @Cron('0 9 * * 1', { timeZone: 'America/Bogota' })  // Lunes 09:00
  @Cron('0 22 L * *', { timeZone: 'America/Bogota' }) // Último día 22:00
  @Cron('0 23 L * *', { timeZone: 'America/Bogota' }) // Último día 23:00
  ```
- **withoutOverlapping:** Implementar mutex con Redis o semáforo en memoria.
- **onOneServer:** Si multi-instancia, usar Redis lock.
- **CRUD Genérico:** `TableCrudService` genera queries dinámicas según nombre de tabla. En TypeORM usar `getRepository(entityName)`.
- **TableValidationService:** Genera reglas de validación desde metadatos de columnas TypeORM.

---

## 16. SPRINT 9 — TOKENS PAT Y SEGURIDAD AVANZADA (Semanas 19-20) <a name="sprint-9"></a>

> **Objetivo:** Migrar Personal Access Tokens, middleware de seguridad avanzada, y asegurar que toda la capa de seguridad esté completa.

### Sprint Backlog

| # | Tarea | SP | Criterio de Aceptación |
|---|-------|----|----------------------|
| 9.1 | Implementar `TokensModule` completo | 2 | 6 endpoints PAT funcionando |
| 9.2 | Implementar `POST /api/v1/tokens` | 1 | Crear PAT con abilities y expiración |
| 9.3 | Implementar `GET /api/v1/tokens` + `GET /{id}` | 1 | Listar tokens activos del usuario |
| 9.4 | Implementar `DELETE /api/v1/tokens/{id}` + `revoke-all` | 1 | Revocar tokens |
| 9.5 | Implementar `POST /api/v1/tokens/{id}/renew` | 1 | Renovar con mismas abilities |
| 9.6 | Implementar `PATAuthGuard` | 1 | Guard que valida PAT además de JWT |
| 9.7 | Implementar `CheckMembershipGuard` | 1 | Valida membresía activa (403) |
| **Total** | | **8** (Sprint más ligero) | |

### Endpoints Migrados

```
GET    /api/v1/tokens                                     ✅
POST   /api/v1/tokens                                     ✅
GET    /api/v1/tokens/{id}                                ✅
DELETE /api/v1/tokens/{id}                                ✅
POST   /api/v1/tokens/{id}/renew                          ✅
POST   /api/v1/tokens/revoke-all                          ✅
```

### Notas de Implementación

- **PAT Format:** `{id}|{random_token_hash}` — Replicar el formato de Laravel Sanctum/Passport.
- **Límites:** 10 tokens/día, 20 activos máximo, expiración máxima 365 días.
- **Abilities:** Array de strings que definen permisos del token.
- **Dual Auth:** El `JwtAuthGuard` debe aceptar tanto JWT de sesión como PAT de larga duración.

---

## 17. SPRINT 10 — TESTING E2E, QA Y DEPLOYMENT (Semanas 21-22) <a name="sprint-10"></a>

> **Objetivo:** Testing end-to-end, ajustes de compatibilidad, documentación Swagger, y preparación para deployment.
> **Estado:** 🟡 En progreso — Infraestructura Docker completada. Tests E2E y CI/CD pendientes.

### Sprint Backlog

| # | Tarea | SP | Estado | Criterio de Aceptación |
|---|-------|----|--------|----------------------|
| 10.1 | Tests E2E de todos los endpoints (Supertest) | 5 | ⏳ Pendiente | Todos los 50+ endpoints testeados contra respuestas Laravel |
| 10.2 | Tests de compatibilidad de respuestas | 3 | ⏳ Pendiente | Comparación automática: respuesta NestJS === respuesta Laravel |
| 10.3 | Tests de paginación Laravel | 2 | ⏳ Pendiente | Formato paginación idéntico en todos los endpoints paginados |
| 10.4 | Configurar Swagger/OpenAPI | 2 | ⏳ Pendiente | Documentación API idéntica a la actual (l5-swagger) |
| 10.5 | Performance testing (Artillery/k6) | 2 | ⏳ Pendiente | Latencia ≤ Laravel en endpoints críticos |
| 10.6 | Docker production config | 2 | ✅ Completado | `Dockerfile` multi-stage, `docker-compose.yml` prod, `docker-compose.dev.yml` dev + Mailpit |
| 10.7 | CI/CD pipeline (GitHub Actions) | 2 | ⏳ Pendiente | Build, test, lint en cada PR |
| 10.8 | Documentación final + runbook migración | 2 | ✅ Completado | `README.md` con guía completa: prereqs, env, comandos, Docker |
| 10.9 | Smoke tests en staging | 1 | ⏳ Pendiente | Frontend Angular funciona contra NestJS |
| **Total** | | **21** | **4 SP ✅ / 17 SP ⏳** | |

### Entregables Sprint 10 (estado actual)

- [x] `Dockerfile` — multi-stage: builder (node:20-alpine) → production (solo dist + templates)
- [x] `docker-compose.yml` — stack producción: API + PostgreSQL 16 + Redis 7 con healthchecks
- [x] `docker-compose.dev.yml` — stack desarrollo: hot-reload + Mailpit (UI en `http://localhost:8025`) + debug port 9229
- [x] `typeorm.config.ts` — `DataSource` independiente para CLI (`migration:generate`, `migration:run`, `migration:revert`)
- [x] `README.md` — prerrequisitos, variables de entorno, scripts, estructura, compatibilidad Laravel
- [x] `src/database/entities/index.ts` — barrel export de las 23 entidades
- [ ] Tests E2E (Supertest) — pendiente
- [ ] Swagger/OpenAPI decorators — pendiente
- [ ] GitHub Actions CI/CD — pendiente
- [ ] Smoke tests en staging — pendiente

### Criterios de Aceptación E2E

```
Para CADA endpoint:
1. ✅ HTTP Method + URI idénticos
2. ✅ Headers requeridos iguales (Authorization, Content-Type)
3. ✅ Body de request con misma estructura
4. ✅ Response status code idéntico
5. ✅ Response body structure idéntica (JSON keys, nesting)
6. ✅ Paginación con mismos campos (current_page, links[], etc.)
7. ✅ Errores de validación con misma estructura (422)
8. ✅ Rate limiting con mismos umbrales
```

---

## 18. RIESGOS Y MITIGACIONES <a name="riesgos-y-mitigaciones"></a>

| # | Riesgo | Probabilidad | Impacto | Mitigación |
|---|--------|-------------|---------|-----------|
| R1 | Formato de token JWT diferente a Laravel Passport | Alta | Crítico | Sprint 1: Investigar formato exacto del token Passport. Opción: mantener Passport OAuth tables en BD y validar tokens existentes, o forzar re-login. |
| R2 | Paginación no 100% compatible | Media | Alto | Sprint 0: Crear tests snapshot comparando output Laravel vs NestJS. |
| R3 | Tablas sin migraciones (schema legacy) | Alta | Medio | Sprint 0: Exportar schema actual con `pg_dump --schema-only` y crear entidades TypeORM manualmente. |
| R4 | Performance inferior con TypeORM vs Eloquent | Media | Medio | Sprint 10: Benchmark comparativo. Optimizar queries con QueryBuilder donde sea necesario. |
| R5 | MIME type validation diferente (finfo vs file-type) | Baja | Medio | Sprint 4: Test matrix con archivos reales de producción. |
| R6 | AWS SDK v3 diferencias con PHP SDK | Media | Medio | Sprint 7: Probar con credenciales reales en staging. |
| R7 | Cron jobs overlap en multi-instancia | Media | Alto | Sprint 8: Implementar distributed locking con Redis. |
| R8 | Email templates visuamente diferentes | Media | Bajo | Sprint 5: Comparar screenshots de emails lado a lado. |

---

## 19. CRITERIOS DE ACEPTACIÓN GLOBAL <a name="criterios-de-aceptación-global"></a>

### Compatibilidad con Frontend Angular

```
✅ TODAS las rutas API idénticas (path + método HTTP)
✅ TODOS los query params soportados idénticos
✅ TODOS los response bodies con misma estructura JSON
✅ Paginación con campos: current_page, last_page, per_page, total, from, to, links[], first_page_url, last_page_url, next_page_url, prev_page_url, path
✅ Errores de validación: { success: false, message, errors: { field: [messages] } }
✅ Status codes idénticos por operación
✅ Auth header: Authorization: Bearer {token}
✅ Content-Type: application/json (y multipart/form-data donde aplique)
✅ CORS configurado igual
✅ Rate limiting con mismos umbrales
```

### Calidad de Código

```
✅ Cobertura de tests ≥ 80% (unit + integration)
✅ 0 errores de ESLint
✅ TypeScript strict mode
✅ Sin console.log/console.error (usar SmartLoggerService)
✅ Sin datos sensibles en logs
✅ Principios SOLID verificados en code review
✅ Clean Architecture: capas claramente separadas
```

---

## 20. DEFINITION OF DONE <a name="definition-of-done"></a>

### Por cada Historia de Usuario:

- [x] Código implementado y compilando sin errores *(Sprints 0-9)*
- [ ] Tests unitarios escritos y pasando (≥ 80% cobertura del componente) *(Sprint 10 pendiente)*
- [x] Endpoint responde con estructura JSON idéntica a Laravel
- [x] Paginación (si aplica) incluye todos los campos Laravel
- [x] Errores de validación siguen formato Laravel 422
- [x] No hay `console.log` — se usa SmartLoggerService
- [x] No hay datos sensibles en logs
- [x] ESLint + Prettier pasan sin errores
- [ ] Code review aprobado (SOLID, Clean Code) *(pendiente revisión)*
- [ ] Documentación Swagger actualizada (si es endpoint nuevo) *(Sprint 10 pendiente)*
- [ ] Test E2E escrito (al menos happy path) *(Sprint 10 pendiente)*

### Por cada Sprint:

- [x] Todos las historias cumpliendo DoD individual *(Sprints 0-9)*
- [ ] Sprint Review ejecutada *(Sprint 10 en curso)*
- [ ] Sprint Retrospective ejecutada *(Sprint 10 en curso)*
- [x] Incremento funcional desplegable (`npm run start:dev` / Docker)
- [ ] Regresión: tests de sprints anteriores siguen pasando *(pendiente — no hay tests aún)*
- [ ] Demo: endpoints funcionando con Postman/frontend Angular *(pendiente staging)*

---

## RESUMEN DE VELOCIDAD Y TIMELINE

| Sprint | Semanas | Story Points | Endpoints | Acumulado |
|--------|---------|-------------|-----------|-----------|
| Sprint | Semanas | Story Points | Endpoints | Acumulado | Estado |
|--------|---------|-------------|-----------|-----------|--------|
| Sprint 0 | 1-2 | 21 | 0 (fundación) | 0 | ✅ Completado |
| Sprint 1 | 3-4 | 21 | 8 (auth) | 8 | ✅ Completado |
| Sprint 2 | 5-6 | 13 | 8 (empresa/maestro) | 16 | ✅ Completado |
| Sprint 3 | 7-8 | 34 | 8 (certificados core) | 24 | ✅ Completado |
| Sprint 4 | 9-10 | 13 | 2 (archivos) | 26 | ✅ Completado |
| Sprint 5 | 11-12 | 21 | 5 (notificaciones) | 31 | ✅ Completado |
| Sprint 6 | 13-14 | 21 | 8 (webhooks) | 39 | ✅ Completado |
| Sprint 7 | 15-16 | 21 | 0 (IA/OCR async) | 39 | ✅ Completado |
| Sprint 8 | 17-18 | 13 | 12 (reports/cron/profile) | 51 | ✅ Completado |
| Sprint 9 | 19-20 | 8 | 6 (PAT) | 57 | ✅ Completado |
| Sprint 10 | 21-22 | 21 | 0 (QA/deploy) | 57 | 🟡 En progreso |
| **Total** | **22 semanas** | **207 SP** | **57 endpoints** | | **186 SP ✅ / 21 SP 🟡** |

### Velocidad real: 186 SP entregados en Sprint 0-9 (código completo)

### Avance: **~90% completado** — Pendiente: Tests E2E, Swagger, CI/CD

### Timeline Total planeado: **~5.5 meses** (22 semanas, 11 sprints de 2 semanas)

---

## DEPENDENCIAS TÉCNICAS (package.json)

```json
{
  "dependencies": {
    "@nestjs/core": "^10.x",
    "@nestjs/common": "^10.x",
    "@nestjs/platform-fastify": "^10.x",
    "@nestjs/typeorm": "^10.x",
    "@nestjs/config": "^3.x",
    "@nestjs/jwt": "^10.x",
    "@nestjs/passport": "^10.x",
    "@nestjs/event-emitter": "^2.x",
    "@nestjs/bull": "^10.x",
    "@nestjs/schedule": "^4.x",
    "@nestjs/swagger": "^7.x",
    "@nestjs-modules/mailer": "^2.x",
    "@fastify/multipart": "^8.x",
    "@fastify/cors": "^9.x",
    "@fastify/helmet": "^11.x",
    "@fastify/rate-limit": "^9.x",
    "typeorm": "^0.3.x",
    "pg": "^8.x",
    "passport-jwt": "^4.x",
    "class-validator": "^0.14.x",
    "class-transformer": "^0.5.x",
    "bull": "^4.x",
    "handlebars": "^4.x",
    "nodemailer": "^6.x",
    "@aws-sdk/client-textract": "^3.x",
    "@google/generative-ai": "^0.x",
    "file-type": "^19.x",
    "node-forge": "^1.x",
    "archiver": "^7.x",
    "pdfkit": "^0.15.x",
    "exceljs": "^4.x",
    "bcrypt": "^5.x",
    "uuid": "^9.x",
    "dayjs": "^1.x",
    "rxjs": "^7.x"
  },
  "devDependencies": {
    "@nestjs/testing": "^10.x",
    "jest": "^29.x",
    "supertest": "^7.x",
    "eslint": "^8.x",
    "prettier": "^3.x",
    "@types/node": "^20.x",
    "typescript": "^5.x",
    "ts-jest": "^29.x"
  }
}
```

---

> **Documento creado para la migración del proyecto Certificate Manager de Laravel a NestJS + Fastify + TypeORM.**
> **Metodología: SCRUM | Principios: SOLID + Clean Code + Clean Architecture**
> **Restricción: Compatibilidad 100% de rutas, respuestas y paginación con el frontend Angular existente.**
