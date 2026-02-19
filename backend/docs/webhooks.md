# Implementación de Webhooks — Certificate Manager

> **Estado:** Propuesta de diseño
> **Versión:** 1.0.0
> **Fecha:** 2026-02-19
> **Branch objetivo:** `feature/webhooks`

---

## Tabla de Contenidos

1. [Contexto y Objetivos](#1-contexto-y-objetivos)
2. [Principios de Diseño](#2-principios-de-diseño)
3. [Eventos Soportados](#3-eventos-soportados)
4. [Arquitectura General](#4-arquitectura-general)
5. [Estructura de Directorios](#5-estructura-de-directorios)
6. [Contratos (Interfaces)](#6-contratos-interfaces)
7. [Modelos y Base de Datos](#7-modelos-y-base-de-datos)
8. [Capa de Servicios](#8-capa-de-servicios)
9. [Jobs y Cola de Entrega](#9-jobs-y-cola-de-entrega)
10. [Listeners — Desacoplamiento de Dominio](#10-listeners--desacoplamiento-de-dominio)
11. [Builders de Payload](#11-builders-de-payload)
12. [API REST de Gestión](#12-api-rest-de-gestión)
13. [Seguridad — Firma HMAC](#13-seguridad--firma-hmac)
14. [Reintentos y Resiliencia](#14-reintentos-y-resiliencia)
15. [Registro en Providers](#15-registro-en-providers)
16. [Configuración](#16-configuración)
17. [Flujo Completo (Diagrama)](#17-flujo-completo-diagrama)
18. [Plan de Implementación](#18-plan-de-implementación)
19. [Consideraciones Futuras](#19-consideraciones-futuras)

---

## 1. Contexto y Objetivos

### Situación Actual

El sistema ya cuenta con:
- **Events/Listeners** internos (`CertificateProcessedWithAI`, `HandleCertificateAIProcessing`)
- **Jobs** asincrónicos con reintentos (`ProcessCertificateJob`)
- **Notificaciones** por email (15 clases en `app/Notifications/`)
- **Queue system** configurado (sync/database/redis)
- **Change History** para auditoría de estados

### Problema

Los sistemas externos (ERP del cliente, plataformas de terceros) no tienen forma de recibir notificaciones en tiempo real cuando ocurren cambios en las solicitudes de certificado.

### Objetivo

Implementar un sistema de **webhooks salientes** que:
- Notifique a URLs externas ante eventos del dominio
- Sea **completamente independiente** del flujo de negocio principal
- Soporte reintentos automáticos con backoff exponencial
- Firme cada entrega con **HMAC-SHA256** para verificación
- Sea configurable **por compañía** (multi-tenant)

---

## 2. Principios de Diseño

### SOLID Aplicado

| Principio | Aplicación en este diseño |
|-----------|--------------------------|
| **S** — Single Responsibility | Cada clase tiene una sola razón para cambiar: `WebhookSigner` solo firma, `WebhookDispatcher` solo orquesta, `DeliverWebhookJob` solo entrega |
| **O** — Open/Closed | Nuevos eventos se agregan implementando `WebhookEventContract` sin tocar código existente |
| **L** — Liskov Substitution | Todos los `PayloadBuilder` son intercambiables; el dispatcher nunca sabe qué builder usa |
| **I** — Interface Segregation | `CanReceiveWebhooks` (tiene endpoints), `HasWebhookSignature` (firma) son contratos separados y mínimos |
| **D** — Dependency Inversion | Los servicios dependen de interfaces (`WebhookRepositoryContract`), no de Eloquent directamente |

### Clean Code

- Nombres expresivos en inglés técnico / español de dominio
- Funciones con una sola responsabilidad (< 20 líneas)
- Sin magic strings — constantes en `WebhookEventType`
- Cero lógica de negocio en controladores
- Cada clase en su namespace correcto

### Desacoplamiento

El dominio **no sabe nada** de webhooks. El acoplamiento se evita mediante:

```
Dominio (Services/Jobs) → Events de Laravel → Listeners de Webhooks → DeliverWebhookJob
```

Los listeners de webhook escuchan eventos **ya existentes** en el sistema. Si mañana se elimina el módulo de webhooks, el dominio no cambia nada.

---

## 3. Eventos Soportados

| Evento (constante) | Trigger | Fuente en código actual |
|-------------------|---------|------------------------|
| `certificate_request.created` | Nueva solicitud creada | `CertificateRequestService::createCertificateRequest()` |
| `certificate_request.status_changed` | Cambio de estado | `CertificateRequestController@updateStatus` + `ChangeHistory` |
| `certificate_request.ai_processed` | Análisis IA completado | `CertificateProcessedWithAI` (event ya existente) |
| `certificate_request.file_uploaded` | Archivo adjunto subido | `CertificateRequestFilesService` |
| `certificate_request.deleted` | Solicitud eliminada | `CertificateRequestController@destroy` |
| `certificate.expiring` | Certificado próximo a vencer | `SendExpiringCertificatesNotificationsJob` |

---

## 4. Arquitectura General

```
┌─────────────────────────────────────────────────────────┐
│                    DOMINIO (sin cambios)                 │
│  CertificateRequestService   ProcessCertificateJob       │
│  CertificateRequestFilesService                         │
└─────────────────┬───────────────────────────────────────┘
                  │  dispara Events de Laravel (existentes
                  │  + nuevos eventos de dominio simples)
                  ▼
┌─────────────────────────────────────────────────────────┐
│              LISTENERS DE WEBHOOK (nueva capa)           │
│  DispatchWebhookOnCertificateCreated                    │
│  DispatchWebhookOnStatusChanged                         │
│  DispatchWebhookOnAIProcessed                           │
│  DispatchWebhookOnFileUploaded                          │
│  DispatchWebhookOnCertificateDeleted                    │
└─────────────────┬───────────────────────────────────────┘
                  │  encolan Jobs
                  ▼
┌─────────────────────────────────────────────────────────┐
│              COLA DE ENTREGA                            │
│  DeliverWebhookJob                                      │
│    └── WebhookDispatcher                                │
│          ├── WebhookEndpointRepository (qué URLs)       │
│          ├── PayloadBuilder (construye body)            │
│          ├── WebhookSigner (firma HMAC)                 │
│          └── HTTP Client (envía)                        │
└─────────────────┬───────────────────────────────────────┘
                  │  registra resultado
                  ▼
┌─────────────────────────────────────────────────────────┐
│              PERSISTENCIA                               │
│  webhook_endpoints   (configuración por compañía)       │
│  webhook_deliveries  (log de cada intento)              │
└─────────────────────────────────────────────────────────┘
```

---

## 5. Estructura de Directorios

```
app/
└── Webhooks/
    ├── Contracts/
    │   ├── WebhookEventContract.php          # Qué es un evento webhook
    │   ├── WebhookPayloadBuilderContract.php  # Cómo se construye el payload
    │   └── WebhookRepositoryContract.php     # Acceso a datos (D de SOLID)
    │
    ├── Enums/
    │   └── WebhookEventType.php              # Constantes de eventos
    │
    ├── Events/                               # Eventos internos del módulo
    │   ├── CertificateRequestCreatedEvent.php
    │   ├── CertificateRequestDeletedEvent.php
    │   └── CertificateFileUploadedEvent.php
    │
    ├── Listeners/                            # Escuchan eventos de dominio
    │   ├── DispatchWebhookOnCertificateCreated.php
    │   ├── DispatchWebhookOnStatusChanged.php
    │   ├── DispatchWebhookOnAIProcessed.php
    │   ├── DispatchWebhookOnFileUploaded.php
    │   └── DispatchWebhookOnCertificateDeleted.php
    │
    ├── Models/
    │   ├── WebhookEndpoint.php               # Configuración del endpoint
    │   └── WebhookDelivery.php               # Log de entregas
    │
    ├── Repositories/
    │   └── WebhookEndpointRepository.php     # Implementación del contrato
    │
    ├── Services/
    │   ├── WebhookService.php                # CRUD de endpoints (casos de uso)
    │   ├── WebhookDispatcher.php             # Orquesta la entrega
    │   └── WebhookSigner.php                 # Firma HMAC-SHA256
    │
    ├── Jobs/
    │   └── DeliverWebhookJob.php             # Job encolable
    │
    ├── Builders/                             # Un builder por evento
    │   ├── CertificateCreatedPayloadBuilder.php
    │   ├── CertificateStatusChangedPayloadBuilder.php
    │   ├── CertificateAIProcessedPayloadBuilder.php
    │   ├── CertificateFileUploadedPayloadBuilder.php
    │   └── CertificateDeletedPayloadBuilder.php
    │
    └── Http/
        ├── Controllers/
        │   └── WebhookEndpointController.php
        └── Requests/
            ├── CreateWebhookEndpointRequest.php
            └── UpdateWebhookEndpointRequest.php

database/
└── migrations/
    ├── 2026_02_19_000001_create_webhook_endpoints_table.php
    └── 2026_02_19_000002_create_webhook_deliveries_table.php

config/
└── webhooks.php
```

---

## 6. Contratos (Interfaces)

### `WebhookEventContract`

```php
<?php

namespace App\Webhooks\Contracts;

interface WebhookEventContract
{
    /**
     * Tipo de evento, ej: "certificate_request.created"
     */
    public function eventType(): string;

    /**
     * ID de la compañía propietaria del recurso afectado.
     * Permite filtrar los endpoints correctos en multi-tenant.
     */
    public function companyId(): int;

    /**
     * Datos del recurso afectado (sin transformar).
     */
    public function resourceData(): array;
}
```

### `WebhookPayloadBuilderContract`

```php
<?php

namespace App\Webhooks\Contracts;

interface WebhookPayloadBuilderContract
{
    /**
     * Evento que este builder sabe construir.
     */
    public function supports(): string;

    /**
     * Construye el payload JSON que recibirá el endpoint externo.
     */
    public function build(WebhookEventContract $event): array;
}
```

### `WebhookRepositoryContract`

```php
<?php

namespace App\Webhooks\Contracts;

use App\Webhooks\Models\WebhookEndpoint;
use Illuminate\Support\Collection;

interface WebhookRepositoryContract
{
    public function findActiveByCompanyAndEvent(int $companyId, string $eventType): Collection;

    public function findById(int $id): ?WebhookEndpoint;

    public function create(array $data): WebhookEndpoint;

    public function update(int $id, array $data): WebhookEndpoint;

    public function delete(int $id): void;

    public function listByCompany(int $companyId): Collection;
}
```

---

## 7. Modelos y Base de Datos

### Migration: `webhook_endpoints`

```php
Schema::create('webhook_endpoints', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
    $table->string('url');
    $table->string('secret', 64);                // Para firma HMAC
    $table->json('events');                      // ["certificate_request.created", ...]
    $table->boolean('is_active')->default(true);
    $table->string('description')->nullable();
    $table->timestamp('last_triggered_at')->nullable();
    $table->unsignedInteger('failure_count')->default(0);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['company_id', 'is_active']);
});
```

### Migration: `webhook_deliveries`

```php
Schema::create('webhook_deliveries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
    $table->string('event_type');
    $table->json('payload');
    $table->string('signature');
    $table->unsignedSmallInteger('http_status')->nullable();
    $table->text('response_body')->nullable();
    $table->enum('status', ['pending', 'delivered', 'failed']);
    $table->unsignedTinyInteger('attempt')->default(1);
    $table->timestamp('delivered_at')->nullable();
    $table->timestamps();

    $table->index(['webhook_endpoint_id', 'status']);
    $table->index('event_type');
});
```

### Modelo `WebhookEndpoint`

```php
<?php

namespace App\Webhooks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Company\Company;

class WebhookEndpoint extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'url', 'secret',
        'events', 'is_active', 'description',
    ];

    protected $casts = [
        'events'    => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    protected $hidden = ['secret'];  // No exponer el secret en JSON

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function listensTo(string $eventType): bool
    {
        return in_array($eventType, $this->events, true)
            || in_array('*', $this->events, true);
    }
}
```

### Modelo `WebhookDelivery`

```php
<?php

namespace App\Webhooks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'webhook_endpoint_id', 'event_type', 'payload',
        'signature', 'http_status', 'response_body',
        'status', 'attempt', 'delivered_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'delivered_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }
}
```

### Enum `WebhookEventType`

```php
<?php

namespace App\Webhooks\Enums;

class WebhookEventType
{
    const CERTIFICATE_CREATED       = 'certificate_request.created';
    const CERTIFICATE_STATUS_CHANGED = 'certificate_request.status_changed';
    const CERTIFICATE_AI_PROCESSED  = 'certificate_request.ai_processed';
    const CERTIFICATE_FILE_UPLOADED = 'certificate_request.file_uploaded';
    const CERTIFICATE_DELETED       = 'certificate_request.deleted';
    const CERTIFICATE_EXPIRING      = 'certificate.expiring';

    public static function all(): array
    {
        return [
            self::CERTIFICATE_CREATED,
            self::CERTIFICATE_STATUS_CHANGED,
            self::CERTIFICATE_AI_PROCESSED,
            self::CERTIFICATE_FILE_UPLOADED,
            self::CERTIFICATE_DELETED,
            self::CERTIFICATE_EXPIRING,
        ];
    }
}
```

---

## 8. Capa de Servicios

### `WebhookSigner`

```php
<?php

namespace App\Webhooks\Services;

class WebhookSigner
{
    private const ALGORITHM = 'sha256';

    public function sign(string $payload, string $secret): string
    {
        return 't=' . time() . ',v1=' . hash_hmac(self::ALGORITHM, $payload, $secret);
    }

    public function verify(string $payload, string $signature, string $secret): bool
    {
        $parts    = $this->parseSignature($signature);
        $expected = hash_hmac(self::ALGORITHM, $payload, $secret);

        return hash_equals($expected, $parts['v1'] ?? '');
    }

    private function parseSignature(string $signature): array
    {
        return collect(explode(',', $signature))
            ->mapWithKeys(fn($part) => explode('=', $part, 2))
            ->toArray();
    }
}
```

> **Nota:** el formato `t={timestamp},v1={hmac}` es compatible con el estándar de Stripe, ampliamente conocido.

### `WebhookDispatcher`

```php
<?php

namespace App\Webhooks\Services;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Contracts\WebhookPayloadBuilderContract;
use App\Webhooks\Contracts\WebhookRepositoryContract;
use App\Webhooks\Models\WebhookDelivery;
use App\Webhooks\Models\WebhookEndpoint;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatcher
{
    public function __construct(
        private readonly WebhookRepositoryContract $repository,
        private readonly WebhookSigner $signer,
        /** @var WebhookPayloadBuilderContract[] */
        private readonly array $builders,
    ) {}

    public function dispatch(WebhookEventContract $event): void
    {
        $endpoints = $this->repository->findActiveByCompanyAndEvent(
            $event->companyId(),
            $event->eventType(),
        );

        if ($endpoints->isEmpty()) {
            return;
        }

        $builder = $this->resolveBuilder($event->eventType());
        $payload = $builder->build($event);

        foreach ($endpoints as $endpoint) {
            $this->deliverToEndpoint($endpoint, $event->eventType(), $payload);
        }
    }

    private function deliverToEndpoint(
        WebhookEndpoint $endpoint,
        string $eventType,
        array $payload,
    ): void {
        $body      = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->signer->sign($body, $endpoint->secret);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_type'          => $eventType,
            'payload'             => $payload,
            'signature'           => $signature,
            'status'              => 'pending',
        ]);

        try {
            $response = $this->sendRequest($endpoint->url, $body, $signature);

            $this->recordSuccess($delivery, $response);
            $endpoint->update(['last_triggered_at' => now(), 'failure_count' => 0]);
        } catch (\Throwable $e) {
            $this->recordFailure($delivery, $e->getMessage());
            $endpoint->increment('failure_count');
            Log::warning("Webhook delivery failed for endpoint {$endpoint->id}: {$e->getMessage()}");
        }
    }

    private function sendRequest(string $url, string $body, string $signature): Response
    {
        return Http::withHeaders([
            'Content-Type'     => 'application/json',
            'X-Webhook-Sig'    => $signature,
            'X-Webhook-Source' => config('app.name'),
        ])
        ->timeout(config('webhooks.timeout', 10))
        ->withBody($body, 'application/json')
        ->post($url);
    }

    private function recordSuccess(WebhookDelivery $delivery, Response $response): void
    {
        $delivery->update([
            'http_status'  => $response->status(),
            'response_body' => substr($response->body(), 0, 1000),
            'status'       => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    private function recordFailure(WebhookDelivery $delivery, string $error): void
    {
        $delivery->update([
            'response_body' => $error,
            'status'        => 'failed',
        ]);
    }

    private function resolveBuilder(string $eventType): WebhookPayloadBuilderContract
    {
        $builder = collect($this->builders)
            ->first(fn($b) => $b->supports() === $eventType);

        if ($builder === null) {
            throw new \RuntimeException("No payload builder registered for event: {$eventType}");
        }

        return $builder;
    }
}
```

### `WebhookService` (casos de uso de gestión)

```php
<?php

namespace App\Webhooks\Services;

use App\Webhooks\Contracts\WebhookRepositoryContract;
use App\Webhooks\Enums\WebhookEventType;
use App\Webhooks\Models\WebhookEndpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebhookService
{
    public function __construct(
        private readonly WebhookRepositoryContract $repository,
    ) {}

    public function createEndpoint(int $companyId, array $data): WebhookEndpoint
    {
        $this->validateEvents($data['events']);

        return $this->repository->create([
            'company_id'  => $companyId,
            'url'         => $data['url'],
            'secret'      => Str::random(40),
            'events'      => $data['events'],
            'description' => $data['description'] ?? null,
            'is_active'   => true,
        ]);
    }

    public function updateEndpoint(int $endpointId, int $companyId, array $data): WebhookEndpoint
    {
        $endpoint = $this->repository->findById($endpointId);

        if ($endpoint === null || $endpoint->company_id !== $companyId) {
            throw new \DomainException('Webhook endpoint not found.');
        }

        if (isset($data['events'])) {
            $this->validateEvents($data['events']);
        }

        return $this->repository->update($endpointId, $data);
    }

    public function rotateSecret(int $endpointId, int $companyId): WebhookEndpoint
    {
        $endpoint = $this->repository->findById($endpointId);

        if ($endpoint === null || $endpoint->company_id !== $companyId) {
            throw new \DomainException('Webhook endpoint not found.');
        }

        return $this->repository->update($endpointId, ['secret' => Str::random(40)]);
    }

    public function deleteEndpoint(int $endpointId, int $companyId): void
    {
        $endpoint = $this->repository->findById($endpointId);

        if ($endpoint === null || $endpoint->company_id !== $companyId) {
            throw new \DomainException('Webhook endpoint not found.');
        }

        $this->repository->delete($endpointId);
    }

    public function listForCompany(int $companyId): Collection
    {
        return $this->repository->listByCompany($companyId);
    }

    private function validateEvents(array $events): void
    {
        $invalid = array_diff($events, WebhookEventType::all());

        if (!empty($invalid)) {
            throw ValidationException::withMessages([
                'events' => 'Tipos de evento inválidos: ' . implode(', ', $invalid),
            ]);
        }
    }
}
```

---

## 9. Jobs y Cola de Entrega

### `DeliverWebhookJob`

```php
<?php

namespace App\Webhooks\Jobs;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Services\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries    = 3;
    public int $timeout  = 30;
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(
        private readonly WebhookEventContract $event,
    ) {
        $this->onQueue(config('webhooks.queue', 'webhooks'));
    }

    public function handle(WebhookDispatcher $dispatcher): void
    {
        $dispatcher->dispatch($this->event);
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('DeliverWebhookJob failed permanently', [
            'event_type' => $this->event->eventType(),
            'company_id' => $this->event->companyId(),
            'error'      => $exception->getMessage(),
        ]);
    }
}
```

> **Cola separada:** se usa la cola `webhooks` para no bloquear otras operaciones críticas. Configurable vía `config('webhooks.queue')`.

---

## 10. Listeners — Desacoplamiento de Dominio

Cada listener escucha un **evento de dominio ya existente** o un nuevo evento de dominio simple, y encola `DeliverWebhookJob`. El dominio no conoce nada de webhooks.

### Ejemplo: `DispatchWebhookOnAIProcessed`

```php
<?php

namespace App\Webhooks\Listeners;

use App\Events\CertificateProcessedWithAI;          // Evento ya existente
use App\Webhooks\Enums\WebhookEventType;
use App\Webhooks\Jobs\DeliverWebhookJob;
use App\Webhooks\ValueObjects\CertificateAIProcessedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DispatchWebhookOnAIProcessed implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'webhooks';

    public function handle(CertificateProcessedWithAI $event): void
    {
        DeliverWebhookJob::dispatch(
            new CertificateAIProcessedEvent(
                certificateRequestId: $event->certificateRequestId,
                companyId: $this->resolveCompanyId($event->certificateRequestId),
                aiResults: $event->aiResults,
                processingTime: $event->processingTime,
            )
        );
    }

    private function resolveCompanyId(int $certificateRequestId): int
    {
        return \App\Models\CertificateRequest::find($certificateRequestId)?->company_id ?? 0;
    }
}
```

### Nuevo Evento de Dominio: `CertificateRequestCreatedEvent`

Para eventos que no tienen un Event de Laravel aún, se crea uno mínimo en el dominio:

```php
<?php

namespace App\Webhooks\Events;

use App\Models\CertificateRequest;
use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Enums\WebhookEventType;

class CertificateRequestCreatedEvent implements WebhookEventContract
{
    public function __construct(
        private readonly CertificateRequest $certificateRequest,
    ) {}

    public function eventType(): string
    {
        return WebhookEventType::CERTIFICATE_CREATED;
    }

    public function companyId(): int
    {
        return $this->certificateRequest->company_id;
    }

    public function resourceData(): array
    {
        return $this->certificateRequest->toArray();
    }
}
```

> Estos eventos **no se disparan desde el dominio**. Los Listeners de webhook usan estos objetos como Data Transfer Objects (DTO). El dominio dispara **sus propios eventos** (que ya existen o se agregan de forma desacoplada usando `event()`).

---

## 11. Builders de Payload

Cada builder produce el JSON que recibirá el sistema externo. Siguen un esquema uniforme con sobre de metadatos.

### Payload estándar

```json
{
  "id": "wh_01HXYZ...",
  "event": "certificate_request.status_changed",
  "created_at": "2026-02-19T10:30:00Z",
  "data": {
    "certificate_request_id": 42,
    "company_id": 7,
    "previous_status": "PENDING",
    "new_status": "ACCEPTED",
    "changed_by_user_id": 15,
    "comment": "Documentación correcta"
  }
}
```

### `CertificateStatusChangedPayloadBuilder`

```php
<?php

namespace App\Webhooks\Builders;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Contracts\WebhookPayloadBuilderContract;
use App\Webhooks\Enums\WebhookEventType;
use Illuminate\Support\Str;

class CertificateStatusChangedPayloadBuilder implements WebhookPayloadBuilderContract
{
    public function supports(): string
    {
        return WebhookEventType::CERTIFICATE_STATUS_CHANGED;
    }

    public function build(WebhookEventContract $event): array
    {
        $data = $event->resourceData();

        return [
            'id'         => 'wh_' . Str::ulid(),
            'event'      => $this->supports(),
            'created_at' => now()->toIso8601String(),
            'data'       => [
                'certificate_request_id' => $data['id'],
                'company_id'             => $event->companyId(),
                'previous_status'        => $data['previous_status'],
                'new_status'             => $data['new_status'],
                'changed_by_user_id'     => $data['changed_by_user_id'] ?? null,
                'comment'                => $data['comment'] ?? null,
            ],
        ];
    }
}
```

---

## 12. API REST de Gestión

### Rutas (añadir a `routes/api.php`)

```php
// Webhook Management (dentro del grupo auth:api existente)
Route::prefix('webhooks')->group(function () {
    Route::get('/',              [WebhookEndpointController::class, 'index']);
    Route::post('/',             [WebhookEndpointController::class, 'store']);
    Route::get('/{id}',          [WebhookEndpointController::class, 'show']);
    Route::put('/{id}',          [WebhookEndpointController::class, 'update']);
    Route::delete('/{id}',       [WebhookEndpointController::class, 'destroy']);
    Route::post('/{id}/rotate',  [WebhookEndpointController::class, 'rotateSecret']);
    Route::get('/events',        [WebhookEndpointController::class, 'availableEvents']);
    Route::get('/{id}/deliveries', [WebhookDeliveryController::class, 'index']);
});
```

### `WebhookEndpointController`

```php
<?php

namespace App\Webhooks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Webhooks\Enums\WebhookEventType;
use App\Webhooks\Http\Requests\CreateWebhookEndpointRequest;
use App\Webhooks\Http\Requests\UpdateWebhookEndpointRequest;
use App\Webhooks\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookEndpointController extends Controller
{
    public function __construct(
        private readonly WebhookService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $endpoints = $this->service->listForCompany($request->user()->company_id);

        return response()->json(['data' => $endpoints]);
    }

    public function store(CreateWebhookEndpointRequest $request): JsonResponse
    {
        $endpoint = $this->service->createEndpoint(
            $request->user()->company_id,
            $request->validated(),
        );

        return response()->json(['data' => $endpoint], 201);
    }

    public function update(UpdateWebhookEndpointRequest $request, int $id): JsonResponse
    {
        $endpoint = $this->service->updateEndpoint(
            $id,
            $request->user()->company_id,
            $request->validated(),
        );

        return response()->json(['data' => $endpoint]);
    }

    public function rotateSecret(Request $request, int $id): JsonResponse
    {
        $endpoint = $this->service->rotateSecret($id, $request->user()->company_id);

        // El secret SÍ se expone en este endpoint (única vez)
        return response()->json([
            'data'   => $endpoint,
            'secret' => $endpoint->secret,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->deleteEndpoint($id, $request->user()->company_id);

        return response()->json(null, 204);
    }

    public function availableEvents(): JsonResponse
    {
        return response()->json(['data' => WebhookEventType::all()]);
    }
}
```

### `CreateWebhookEndpointRequest`

```php
<?php

namespace App\Webhooks\Http\Requests;

use App\Webhooks\Enums\WebhookEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateWebhookEndpointRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'url'         => ['required', 'url', 'max:500'],
            'events'      => ['required', 'array', 'min:1'],
            'events.*'    => ['required', 'string', Rule::in(WebhookEventType::all())],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

---

## 13. Seguridad — Firma HMAC

### Cómo verificar en el receptor

```php
// Ejemplo PHP para el sistema receptor
function verifyWebhookSignature(string $payload, string $signatureHeader, string $secret): bool
{
    $parts     = [];
    $timestamp = null;
    $received  = null;

    foreach (explode(',', $signatureHeader) as $part) {
        [$key, $value] = explode('=', $part, 2);
        if ($key === 't')  $timestamp = $value;
        if ($key === 'v1') $received  = $value;
    }

    // Prevenir replay attacks (5 minutos de tolerancia)
    if (abs(time() - (int) $timestamp) > 300) {
        return false;
    }

    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $received ?? '');
}
```

### Headers de cada entrega

```
POST https://sistema-externo.com/webhook
Content-Type: application/json
X-Webhook-Sig: t=1708345200,v1=a4b2c8d1e5f3...
X-Webhook-Source: Certificate Manager
X-Webhook-Event: certificate_request.status_changed
```

### Buenas prácticas incluidas

- Secret **nunca** se expone en listados (`$hidden = ['secret']`)
- Secret se genera con `Str::random(40)` (criptográficamente seguro)
- Endpoint de rotación de secret disponible
- Timestamp en firma para prevenir **replay attacks**
- `hash_equals()` para comparación en tiempo constante

---

## 14. Reintentos y Resiliencia

### Estrategia de backoff

| Intento | Espera | Acción |
|---------|--------|--------|
| 1 | inmediato | Primer intento |
| 2 | 60 segundos | Reintento automático |
| 3 | 5 minutos | Reintento automático |
| Fallido | — | `failed()` → Log + incrementa `failure_count` |

### Auto-desactivación

Si `failure_count` supera el umbral configurado (ej. 10), el endpoint se puede desactivar automáticamente. Esto se implementa en `WebhookDispatcher`:

```php
// En deliverToEndpoint, tras incrementar failure_count:
if ($endpoint->failure_count >= config('webhooks.max_failures', 10)) {
    $endpoint->update(['is_active' => false]);
    Log::warning("Webhook endpoint {$endpoint->id} auto-disabled after repeated failures.");
}
```

### Registro de entregas

Cada intento queda en `webhook_deliveries`:
- `status`: `pending` → `delivered` / `failed`
- `http_status`: código HTTP de respuesta
- `response_body`: primeros 1000 chars de la respuesta
- `attempt`: número de intento

---

## 15. Registro en Providers

### `EventServiceProvider` — Añadir listeners

```php
// En $listen del EventServiceProvider existente (app/Providers/EventServiceProvider.php)
protected $listen = [
    // ... listeners existentes ...

    // Nuevos listeners de webhook (escuchan eventos ya existentes)
    \App\Events\CertificateProcessedWithAI::class => [
        \App\Listeners\HandleCertificateAIProcessing::class, // existente
        \App\Webhooks\Listeners\DispatchWebhookOnAIProcessed::class, // nuevo
    ],

    // Eventos nuevos de dominio (se disparan desde services existentes)
    \App\Webhooks\Events\CertificateRequestCreatedEvent::class => [
        \App\Webhooks\Listeners\DispatchWebhookOnCertificateCreated::class,
    ],
    \App\Webhooks\Events\CertificateStatusChangedEvent::class => [
        \App\Webhooks\Listeners\DispatchWebhookOnStatusChanged::class,
    ],
    \App\Webhooks\Events\CertificateFileUploadedEvent::class => [
        \App\Webhooks\Listeners\DispatchWebhookOnFileUploaded::class,
    ],
    \App\Webhooks\Events\CertificateRequestDeletedEvent::class => [
        \App\Webhooks\Listeners\DispatchWebhookOnCertificateDeleted::class,
    ],
];
```

### `AppServiceProvider` — Binding de contratos

```php
// En AppServiceProvider::register()
$this->app->bind(
    \App\Webhooks\Contracts\WebhookRepositoryContract::class,
    \App\Webhooks\Repositories\WebhookEndpointRepository::class,
);

// Registro de builders (permite agregar nuevos sin tocar el Dispatcher)
$this->app->when(\App\Webhooks\Services\WebhookDispatcher::class)
    ->needs('$builders')
    ->give([
        new \App\Webhooks\Builders\CertificateCreatedPayloadBuilder(),
        new \App\Webhooks\Builders\CertificateStatusChangedPayloadBuilder(),
        new \App\Webhooks\Builders\CertificateAIProcessedPayloadBuilder(),
        new \App\Webhooks\Builders\CertificateFileUploadedPayloadBuilder(),
        new \App\Webhooks\Builders\CertificateDeletedPayloadBuilder(),
    ]);
```

---

## 16. Configuración

### `config/webhooks.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cola de entrega de webhooks
    |--------------------------------------------------------------------------
    | Se recomienda una cola dedicada para no interferir con otras operaciones.
    */
    'queue' => env('WEBHOOK_QUEUE', 'webhooks'),

    /*
    |--------------------------------------------------------------------------
    | Timeout en segundos para peticiones HTTP salientes
    |--------------------------------------------------------------------------
    */
    'timeout' => env('WEBHOOK_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Máximo de fallos consecutivos antes de desactivar un endpoint
    |--------------------------------------------------------------------------
    */
    'max_failures' => env('WEBHOOK_MAX_FAILURES', 10),

    /*
    |--------------------------------------------------------------------------
    | Máximo de endpoints por compañía
    |--------------------------------------------------------------------------
    */
    'max_endpoints_per_company' => env('WEBHOOK_MAX_ENDPOINTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Retención de logs de entrega en días
    |--------------------------------------------------------------------------
    */
    'delivery_log_retention_days' => env('WEBHOOK_LOG_RETENTION', 30),
];
```

### Variables `.env` a agregar

```env
WEBHOOK_QUEUE=webhooks
WEBHOOK_TIMEOUT=10
WEBHOOK_MAX_FAILURES=10
WEBHOOK_MAX_ENDPOINTS=5
WEBHOOK_LOG_RETENTION=30
```

---

## 17. Flujo Completo (Diagrama)

```
┌──────────────────────────────────────────────────────────────────┐
│ 1. ACCIÓN DE USUARIO                                             │
│    PUT /v1/certificate-request/{id}/status                       │
│         ↓                                                        │
│    CertificateRequestController::updateStatus()                  │
│         ↓                                                        │
│    (lógica de negocio — sin cambios)                             │
│         ↓                                                        │
│    event(new CertificateStatusChangedEvent(...))  ← nuevo        │
└──────────────────────────────┬───────────────────────────────────┘
                               │
                               ▼ Laravel Event Bus
┌──────────────────────────────────────────────────────────────────┐
│ 2. LISTENER (ShouldQueue → cola 'webhooks')                      │
│    DispatchWebhookOnStatusChanged::handle()                      │
│         ↓                                                        │
│    DeliverWebhookJob::dispatch(WebhookEventContract)             │
└──────────────────────────────┬───────────────────────────────────┘
                               │
                               ▼ Queue Worker
┌──────────────────────────────────────────────────────────────────┐
│ 3. JOB (async, reintentos automáticos)                           │
│    DeliverWebhookJob::handle(WebhookDispatcher)                  │
│         ↓                                                        │
│    WebhookDispatcher::dispatch()                                 │
│    ├── WebhookEndpointRepository → endpoints activos de company  │
│    ├── PayloadBuilder → construye JSON                           │
│    ├── WebhookSigner → firma HMAC                                │
│    └── Http::post(url, payload, headers)                         │
│         ↓                                                        │
│    WebhookDelivery::create() → registra resultado                │
└──────────────────────────────────────────────────────────────────┘
```

---

## 18. Plan de Implementación

### Fase 1 — Infraestructura base (sin tocar dominio)

- [ ] Crear `config/webhooks.php`
- [ ] Crear migrations (`webhook_endpoints`, `webhook_deliveries`)
- [ ] Crear `WebhookEndpoint` y `WebhookDelivery` (modelos)
- [ ] Crear contratos: `WebhookEventContract`, `WebhookPayloadBuilderContract`, `WebhookRepositoryContract`
- [ ] Crear `WebhookEventType` enum
- [ ] Implementar `WebhookSigner`
- [ ] Implementar `WebhookEndpointRepository`
- [ ] Binding en `AppServiceProvider`

### Fase 2 — Entrega

- [ ] Implementar `WebhookDispatcher`
- [ ] Implementar `DeliverWebhookJob`
- [ ] Crear todos los `PayloadBuilder` (5 builders)
- [ ] Crear Value Objects para cada evento

### Fase 3 — Desacoplamiento del dominio

- [ ] Crear 4 eventos simples de dominio (los que no existen aún)
- [ ] Disparar eventos desde `CertificateRequestService` y `CertificateRequestFilesService`
- [ ] Crear 5 Listeners webhook
- [ ] Registrar en `EventServiceProvider`
- [ ] Conectar listener a `CertificateProcessedWithAI` existente

### Fase 4 — API de gestión

- [ ] `CreateWebhookEndpointRequest` y `UpdateWebhookEndpointRequest`
- [ ] `WebhookEndpointController` y `WebhookDeliveryController`
- [ ] Registrar rutas en `routes/api.php`
- [ ] Documentar con Swagger

### Fase 5 — Operaciones

- [ ] Artisan command: `webhook:cleanup` (limpia deliveries viejos)
- [ ] Artisan command: `webhook:retry {delivery_id}` (reintento manual)
- [ ] Test unitario para `WebhookSigner`
- [ ] Test de integración para `WebhookDispatcher`
- [ ] Agregar variables a `.env.example`

---

## 19. Consideraciones Futuras

| Feature | Descripción | Prioridad |
|---------|-------------|-----------|
| **Dashboard de entregas** | UI para ver historial de webhooks | Media |
| **Retry manual** | Endpoint `POST /webhooks/deliveries/{id}/retry` | Alta |
| **Webhook de prueba** | `POST /webhooks/{id}/test` envía payload de prueba | Media |
| **Rate limiting** | Límite de entregas por endpoint por hora | Media |
| **IP allowlist** | Filtrar IPs receptoras permitidas | Baja |
| **Suscripción a `*`** | Un endpoint recibe todos los eventos | Baja |
| **Transformación de payload** | Templates Jinja/Blade para formato custom | Baja |

---

> **Nota de implementación:** Los cambios al dominio existente son mínimos — solo agregar llamadas a `event()` en `CertificateRequestService` y `CertificateRequestFilesService`. Todo lo demás es código nuevo en `app/Webhooks/`, sin modificar nada existente.
