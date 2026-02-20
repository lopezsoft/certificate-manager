# 🔍 ANÁLISIS PROFUNDO DEL PROYECTO — Certificate Manager

**Fecha de Análisis**: 20 de Febrero de 2026
**Versión del Sistema**: 1.8.0
**Framework**: Laravel 10
**PHP**: 8.1
**Branch activo**: `feature/testing-setup`
**Analista**: GitHub Copilot (Asistente Experto FullStack)

> **Nota de alcance**: La integración de IA (Google Vision, Gemini, AWS Textract) está fuera del alcance actual de mejoras. Los módulos de IA existen en el código pero no se priorizan ni se incluyen en el backlog de trabajo.

---

## 📊 RESUMEN EJECUTIVO

### 🎯 Puntuación Global: **74/100** _(+4 respecto al análisis de Oct 2025)_

| Categoría | Puntuación | Estado | Δ Oct-2025 |
|-----------|------------|--------|------------|
| **Arquitectura** | 75/100 | 🟡 Buena con deuda técnica | = |
| **Funcionalidades** | 92/100 | 🟢 Muy completo | +12 |
| **Código** | 72/100 | 🟡 Funcional con deuda técnica | = |
| **Seguridad** | 65/100 | 🟠 Riesgos moderados | = |
| **Mantenibilidad** | 80/100 | 🟢 Aceptable | = |
| **Testing** | 20/100 | 🔴 Crítico — Requiere atención | +10 |
| **DevOps** | 70/100 | 🟡 Configuración básica | = |
| **Documentación** | 92/100 | 🟢 Excelente | +7 |

---

## 📦 MÓDULOS IMPLEMENTADOS — ESTADO POR VERSIÓN

| Versión | Módulo | Estado |
|---------|--------|--------|
| **core** | Gestión de certificados (CRUD, archivos, PDF, Excel) | ✅ Completo |
| **core** | Autenticación OAuth (Laravel Passport) | ✅ Completo |
| **core** | Sistema de analytics con token interno | ✅ Completo |
| **v1.6.0** | Webhooks salientes multi-tenant (HMAC-SHA256, reintentos, cleanup) | ✅ Completo |
| **v1.7.0** | Personal Access Tokens (PAT) — gestión, renovación, revocación | ✅ Completo |
| **v1.8.0** | Notificaciones de vencimientos (diarias, semanales, mensuales) | ✅ Completo |
| **v1.8.0** | Endpoints API para SPA — vencimientos y notificaciones in-app | ✅ Completo |
| **v1.8.0** | Comandos Artisan para trigger manual de notificaciones | ✅ Completo |
| *ignorado* | Integración IA (Google Vision / Gemini / AWS Textract) | ⛔ Fuera de alcance |

---

## 1️⃣ ARQUITECTURA DEL PROYECTO

### ✅ Fortalezas

#### 1.1 Estructura Organizada

```
app/
├── Common/          ✅ Helpers y utilidades centralizadas
├── Core/            ✅ Clases base y abstracciones
├── DTOs/            ✅ Data Transfer Objects
├── Enums/           ✅ Enumeraciones tipadas
├── Events/          ✅ Event-Driven Architecture
├── Jobs/            ✅ 5 jobs de cola (notificaciones + IA latente)
├── Listeners/       ✅ Manejo desacoplado de eventos
├── Notifications/   ✅ 15 notificaciones (mail + database)
├── Services/        ✅ 16 servicios especializados
├── Queries/         ✅ Separación parcial de consultas
├── Interfaces/      ✅ Contratos definidos
├── Validators/      ✅ Validación centralizada
└── Webhooks/        ✅ Módulo autónomo (Builders, Contracts, Jobs, Repos...)
```

El módulo `Webhooks/` es el mejor estructurado del proyecto: aplica SOLID con interfaces, builders de payload, repository pattern completo y jobs independientes del dominio principal.

### 🚨 Debilidades Arquitectónicas

#### 1.2 Instanciación Manual de Servicios en Controllers

```php
// ❌ ANTIPATRÓN — acopla e impide testing
public function createCertificateRequest(Request $request): JsonResponse
{
    return (new CertificateRequestService())->createCertificateRequest($request);
}
```

**Impacto**: imposibilita mockear dependencias en tests, viola el principio D de SOLID.

```php
// ✅ CORRECTO: Dependency Injection
class CertificateRequestController extends Controller
{
    public function __construct(
        private readonly CertificateRequestService $service
    ) {}
}
// Registrar en AppServiceProvider:
// $this->app->singleton(CertificateRequestService::class);
```

#### 1.3 Uso Excesivo de `DB::table()` Directo (30+ ocurrencias)

```php
// ❌ Pierde ventajas de Eloquent, dificulta factories en tests
$query = DB::table('certificate_requests_years_view')
    ->where('company_id', $company->id)
    ->get();

// ✅ Usar Eloquent con Scopes
CertificateRequest::query()->byCompany($id)->get();
```

#### 1.4 Repository Pattern Incompleto

- ✅ Existe `app/Queries/` (buen intento)
- ❌ No hay interfaces de repositorio formales
- ❌ Lógica de consultas mezclada en Services

---

## 2️⃣ CALIDAD DEL CÓDIGO

### ✅ Aspectos Positivos

- **Logging estructurado** con contexto completo (error, file, line, trace)
- **Validaciones con mensajes en español** centralizadas
- **Jobs con retry logic** (3 intentos, backoff exponencial)
- **Anti-duplicados en notificaciones** via Cache (TTL 24h)
- **Módulo Webhooks** sigue Clean Code y SOLID rigurosamente

### 🚨 Problemas Detectados

#### 2.1 `CertificateRequestService` — God Object (489 líneas)

El método `createCertificateRequest()` supera 200 líneas con responsabilidades mixtas: validación + archivos + Excel + transacción DB + notificaciones. Viola SRP y OCP.

**Refactorización recomendada**: Command Pattern con handlers especializados.

```php
// ✅ Command + Handler
class CreateCertificateRequestCommand
{
    public function __construct(
        public readonly array $data,
        public readonly array $files
    ) {}
}

class CreateCertificateRequestHandler
{
    public function __construct(
        private readonly FileUploadService $fileUploader,
        private readonly CertificateRepository $repository,
        private readonly NotificationService $notifier
    ) {}

    public function handle(CreateCertificateRequestCommand $command): CertificateRequest
    {
        return DB::transaction(function () use ($command) {
            $certificate = $this->repository->create($command->data);
            $this->fileUploader->uploadFiles($certificate, $command->files);
            $this->notifier->notifyCreation($certificate);
            return $certificate;
        });
    }
}
```

#### 2.2 Manejo de Errores Inconsistente

| Patrón encontrado | Estado |
|-------------------|--------|
| `throw new Exception(...)` | ✅ Correcto |
| `return` silencioso tras `Log::error()` | ❌ Oculta fallos |
| `try/catch` sin re-throw | ❌ Traga errores |

**Solución**: jerarquía de excepciones custom + handler global.

```php
// Jerarquía propuesta
class CertificateException extends RuntimeException {}
class InvalidFileException extends CertificateException {}
class EmailNotConfiguredException extends CertificateException {}

// Handler global en app/Exceptions/Handler.php
if ($e instanceof CertificateException) {
    return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
        'type' => class_basename($e),
    ], $e->getCode() ?: 500);
}
```

---

## 3️⃣ SEGURIDAD

###  Validación de Input Incompleta

```php
// ❌ Campo `info` sin sanitizar
'info' => $request->input('info'),

// ✅ Usar strip_tags sobre datos validados
'info' => strip_tags($request->validated()['info']),
```

### 🟠 File Upload Sin Validación de MIME Real

Solo se valida tamaño (2 MB). Falta validar el MIME type real del contenido binario, no solo la extensión declarada.

### 🟠 Sin Rate Limiting en Endpoints de API

No se detectó middleware `throttle` en `routes/api.php`. Endpoints sensibles (subida de archivos, envío de correos) deben limitarse.

```php
// routes/api.php
Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {
    // rutas generales
});

Route::middleware(['auth:api', 'throttle:10,1'])->group(function () {
    Route::post('/{id}/send-mail', ...);
});
```

---

## 4️⃣ TESTING — ESTADO ACTUAL

### 📊 Cobertura Estimada: ~15% 🔴

| Suite | Archivos | Estado |
|-------|---------|--------|
| Unit/Common | `HttpResponseMessages`, `MessageExceptionResponse`, `VerificationDigit` | ✅ |
| Unit/Middleware | `ValidateFileMimeType` | ✅ |
| Unit/Services | `CertificateValidatorService`, `ZipExtractorService` | ✅ |
| Feature/Auth | 5 tests (registro, login, reset, verificación) | ✅ |
| Feature | `ProfileTest` | ⚠️ Básico |
| Manual | `ManualTestCertificateNotifications`, `ManualTestMonthlyReports` | ❌ No automatizados |

### ❌ Funcionalidades Críticas SIN Cobertura

| Componente | Líneas | Coverage |
|------------|--------|---------|
| `CertificateRequestService` | 489 | 0% |
| `SendExpiringCertificatesNotificationsJob` | — | 0% |
| `SendAdminExpiringCertificatesReportJob` | — | 0% |
| `SendMonthlyCompanyCertificatesReportJob` | — | 0% |
| `SendMonthlyAdminCertificatesReportJob` | — | 0% |
| `NotificationController` (v1.8.0 — 5 endpoints) | — | 0% |
| Módulo `Webhooks/` completo | — | 0% |
| PAT / `TokenController` endpoints | — | 0% |

### 💡 Principio rector: Tests con Mocks — sin DB ni migraciones

> **Regla obligatoria**: Todos los tests deben usar **mocks y fakes** de Laravel. Está **prohibido** usar `RefreshDatabase`, `DatabaseMigrations` o factories que ejecuten `INSERT` reales. La base de datos de desarrollo no debe verse afectada en ningún caso.

**Herramientas permitidas:**
- `Mockery::mock()` / `$this->mock()` para servicios y repositorios
- `Notification::fake()`, `Queue::fake()`, `Event::fake()`, `Mail::fake()` para side effects
- `$this->partialMock()` para mockear métodos específicos
- Objetos DTO o `stdClass` para simular entidades sin tocar Eloquent

```php
// tests/Unit/Jobs/SendExpiringCertificatesNotificationsJobTest.php
class SendExpiringCertificatesNotificationsJobTest extends TestCase
{
    // ✅ Sin RefreshDatabase — sin migraciones — sin INSERT real

    public function test_sends_notifications_to_companies_with_expiring_certificates(): void
    {
        Notification::fake();

        // Mock del repositorio — no toca la DB
        $company = (object) ['id' => 1, 'email' => 'test@example.com', 'name' => 'Empresa X'];
        $certificate = (object) [
            'id'             => 10,
            'company_id'     => 1,
            'expiration_date'=> now()->addDays(15),
            'request_status' => 'PROCESSED',
            'company'        => $company,
        ];

        $repositoryMock = $this->mock(CertificateRequestRepositoryInterface::class);
        $repositoryMock->shouldReceive('getExpiringCertificates')
            ->once()
            ->andReturn(collect([$certificate]));

        $job = new SendExpiringCertificatesNotificationsJob($repositoryMock);
        $job->handle();

        Notification::assertSentTo($company, CertificateExpiringNotification::class);
    }

    public function test_does_not_notify_cancelled_requests(): void
    {
        Notification::fake();

        $repositoryMock = $this->mock(CertificateRequestRepositoryInterface::class);
        $repositoryMock->shouldReceive('getExpiringCertificates')
            ->once()
            ->andReturn(collect([])); // repositorio ya filtra por PROCESSED

        $job = new SendExpiringCertificatesNotificationsJob($repositoryMock);
        $job->handle();

        Notification::assertNothingSent();
    }
}
```

```php
// tests/Unit/Services/CertificateRequestServiceTest.php
class CertificateRequestServiceTest extends TestCase
{
    // ✅ Sin RefreshDatabase — mocks puros

    public function test_creates_certificate_request_successfully(): void
    {
        $repoMock = $this->mock(CertificateRequestRepositoryInterface::class);
        $repoMock->shouldReceive('create')->once()->andReturn((object) ['id' => 1]);

        $notifierMock = $this->mock(CertificateRequestMailService::class);
        $notifierMock->shouldReceive('sendCreationMail')->once();

        $service = new CertificateRequestService($repoMock, $notifierMock);
        $result  = $service->createCertificateRequest($this->validRequestData());

        $this->assertNotNull($result);
    }

    private function validRequestData(): array
    {
        return [
            'company_name'       => 'Test Company',
            'dni'                => '900123456',
            'city_id'            => 1,
            'legal_representative' => 'John Doe',
        ];
    }
}
```

---

## 5️⃣ DEVOPS Y CONFIGURACIÓN

### ✅ Aspectos Positivos

- `Dockerfile` + `compose.yaml` presentes
- Queue system configurado (`QUEUE_CONNECTION=database`)
- Colas específicas: `notifications`, `reports`
- Scheduled tasks con `withoutOverlapping(30)` y `onOneServer()`
- Logs dedicados por proceso en `storage/logs/`

### 🚨 Pendientes para Producción

#### 5.1 Sin Configuración de Supervisor

Los jobs nunca se procesarán sin un worker permanente:

```ini
; /etc/supervisor/conf.d/cm-worker.conf
[program:cm-worker]
command=php /var/www/html/backend/artisan queue:work database --queue=notifications,reports --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
stdout_logfile=/var/www/html/backend/storage/logs/worker.log
```

#### 5.2 Sin Cron para Scheduled Tasks

```bash
# Agregar al crontab del servidor
* * * * * cd /var/www/html/backend && php artisan schedule:run >> /dev/null 2>&1
```

#### 5.3 MySQL en Modo No Estricto

```php
// config/database.php — cambiar:
'strict' => false  →  'strict' => true
```

#### 5.4 `APP_VERSION` Desactualizada en `.env`

```dotenv
# Actual (incorrecto)
APP_VERSION="1.1.0"

# Correcto
APP_VERSION="1.8.0"
```

---

## 6️⃣ DOCUMENTACIÓN

### ✅ Estado: Excelente (92/100)

| Documento | Ubicación | Estado |
|-----------|-----------|--------|
| Análisis profundo del proyecto | `docs/ANALISIS_PROYECTO_PROFUNDO.md` | ✅ |
| Informe sistema notificaciones | `docs/2026-02-19-INFORME-SISTEMA-NOTIFICACIONES-VENCIMIENTOS.md` | ✅ |
| Scheduled tasks | `docs/SCHEDULED_TASKS_CERTIFICATES.md` | ✅ |
| Guía PAT para desarrolladores | `docs/2026-01-27-10-30-GUIA-USO-API-TOKENS-DESARROLLADORES.md` | ✅ |
| Refresh tokens en PAT | `docs/2026-01-27-13-30-REFRESH-TOKENS-EN-SISTEMAS-PAT.md` | ✅ |
| Protección endpoints analytics | `docs/ANALYTICS-TOKEN-PROTECTION.md` | ✅ |
| Webhooks — diseño y guía frontend | `docs/webhooks.md` + `docs/webhooks-frontend.md` | ✅ |
| Integración PAT | `docs/pat-integration.md` | ✅ |
| Configuración de uploads | `docs/FILE_UPLOAD_CONFIGURATION.md` | ✅ |

**Pendiente**: `docs/DEPLOYMENT.md` — guía de puesta en producción (Supervisor + Cron + variables de entorno).

---

## 7️⃣ BACKLOG DE TAREAS PENDIENTES

### 🔴 Alta Prioridad — Testing (Sprint 1)

> **Prerequisito**: refactorizar controllers para DI antes de escribir tests de controladores o servicios.
>
> ⚠️ **Regla obligatoria de todos los tests**: usar exclusivamente **mocks, fakes y stubs** de Laravel/Mockery.  
> **Prohibido**: `RefreshDatabase`, `DatabaseMigrations`, `DatabaseTransactions`, `factory()->create()` o cualquier operación que ejecute SQL real. La base de datos de desarrollo **nunca** debe verse afectada por la suite de tests.

| # | Tarea | Estrategia de mock | Complejidad |
|---|-------|--------------------|-------------|
| T-01 | Refactorizar controllers para usar Dependency Injection | — | Media |
| T-02 | Tests unitarios para `SendExpiringCertificatesNotificationsJob` | `Notification::fake()` + mock repositorio | Alta |
| T-03 | Tests unitarios para `SendMonthlyCompanyCertificatesReportJob` | `Mail::fake()` + mock repositorio | Alta |
| T-04 | Tests unitarios para `SendAdminExpiringCertificatesReportJob` | `Mail::fake()` + mock repositorio | Media |
| T-05 | Tests para `NotificationController` (5 endpoints v1.8.0) | `$this->mock(Service)` + `actingAs` con usuario stub | Alta |
| T-06 | Tests para endpoints PAT (`/v1/tokens`) | `$this->mock(TokenService)` + `actingAs` | Media |
| T-07 | Tests para módulo Webhooks (dispatch + delivery) | `Queue::fake()` + `Event::fake()` + mock dispatcher | Alta |
| T-08 | Automatizar `ManualTestCertificateNotifications` y `ManualTestMonthlyReports` | `Notification::fake()` + mock queries | Media |

**Meta Sprint 1**: alcanzar **40% de cobertura**.

### 🟠 Media Prioridad — Calidad de Código (Sprint 2)

| # | Tarea | Complejidad |
|---|-------|-------------|
| T-09 | Crear jerarquía de excepciones custom (`CertificateException`, etc.) | Media |
| T-10 | Implementar handler global en `app/Exceptions/Handler.php` | Baja |
| T-11 | Añadir middleware `throttle` a endpoints sensibles (uploads, mail) | Baja |
| T-12 | Sanitizar inputs sin `strip_tags` (campo `info` y similares) | Baja |
| T-13 | Validación de MIME type real en uploads | Media |
| T-14 | Refactorizar `CertificateRequestService` con Command Pattern | Alta |

### 🟡 Baja Prioridad — Deuda Técnica (Sprint 3+)

| # | Tarea | Complejidad |
|---|-------|-------------|
| T-15 | Migrar `DB::table()` críticos a Eloquent con Scopes | Alta |
| T-16 | Implementar interfaces Repository formales para consultas complejas | Alta |
| T-17 | Resolver consultas N+1 con eager loading (`with([...])`) | Media |
| T-18 | Configurar Supervisor para queue workers en producción | Baja |
| T-19 | Configurar Cron para scheduled tasks en producción | Baja |
| T-20 | Habilitar `strict` mode en MySQL (`config/database.php`) | Baja |
| T-21 | Crear `docs/DEPLOYMENT.md` — guía completa de puesta en producción | Baja |
| T-22 | Configurar GitHub Actions con PHPUnit para CI/CD | Media |
| T-23 | Corregir `APP_VERSION` en `.env` de `1.1.0` a `1.8.0` | Trivial |

---

## 🎯 MÉTRICAS DE ÉXITO

| Métrica | Estado actual | Meta Sprint 1 | Meta 3 meses |
|---------|--------------|---------------|--------------|
| **Test Coverage** | ~15% | 40% | 70% |
| **DI en Controllers** | 0% | 100% | 100% |
| **Rate limiting activo** | No | Sí | Sí |
| **Excepciones custom** | No | Sí | Sí |
| **Supervisor configurado** | No | — | Sí |
| **CI/CD con PHPUnit** | No | — | Sí |
| **Security Score** | 65/100 | 75/100 | 90/100 |

---

## 📝 CONCLUSIÓN

El proyecto alcanzó la **v1.8.0 con todas las funcionalidades core completas**: gestión de certificados, webhooks multi-tenant, PAT, notificaciones de vencimientos, informes mensuales y comandos Artisan para operación manual. La arquitectura es sólida y la documentación de primer nivel.

**El único punto bloqueante antes de un despliegue a producción confiable es el testing automatic.**

La ausencia de pruebas automatizadas convierte cada cambio en un riesgo no medible. El ciclo recomendado para el próximo sprint:

> **1.** Refactorizar DI en controllers → **2.** Escribir tests (Jobs + Feature endpoints) → **3.** Añadir throttling y sanitización → **4.** CI básico con GitHub Actions

La integración de IA queda como módulo latente en el código y podrá activarse en un sprint futuro sin afectar el dominio principal.

---

**Actualizado por**: GitHub Copilot
**Fecha de actualización**: 20 de Febrero de 2026
**Versión del documento**: 2.0

