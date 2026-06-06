# Deploy — Endpoint de Sincronización de Cuentas

> **Endpoint:** `POST /api/v1/sync-account`  
> **Fecha:** 2026-06-06

---

## Configuración en Certificate Manager (destino)

### 1. Variables de entorno

Agregar al `.env`:

```env
SYNC_API_KEY=<generar>
SYNC_API_SECRET=<generar>
SYNC_ALLOWED_IPS=              # Opcional: IPs del servidor origen separadas por coma
```

Generar credenciales:

```bash
php -r "echo 'cm-sync-' . bin2hex(random_bytes(16)) . PHP_EOL;"   # API Key
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"                  # Secret
```

### 2. Limpiar cache

```bash
php artisan config:clear
php artisan route:clear
```

### 3. Archivos involucrados

| Archivo | Acción |
|---|---|
| `app/Http/Middleware/ValidateSyncSignature.php` | Nuevo — Valida HMAC |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | Método `syncAccount()` |
| `app/Http/Kernel.php` | Middleware `sync.signature` registrado |
| `config/services.php` | Config `sync` agregada |
| `routes/auth-api.php` | Ruta registrada |

---

## Configuración en el Sistema Origen (ERP / API)

### 1. Variables de entorno

Agregar las **mismas credenciales** generadas arriba:

```env
CM_SYNC_API_KEY=<mismo valor que SYNC_API_KEY>
CM_SYNC_SECRET=<mismo valor que SYNC_API_SECRET>
CM_SYNC_URL=https://cm-api.produccion.com/api/v1/sync-account
```

### 2. Servicio de sincronización

Crear un servicio en el ERP que envíe la cuenta al CM:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CertificateManagerSyncService
{
    private string $url;
    private string $apiKey;
    private string $secret;

    public function __construct()
    {
        $this->url    = config('services.cm.sync_url');
        $this->apiKey = config('services.cm.sync_api_key');
        $this->secret = config('services.cm.sync_secret');
    }

    /**
     * Sincroniza un usuario y su empresa con Certificate Manager.
     *
     * @param \App\Models\User    $user    Usuario del ERP
     * @param \App\Models\Company $company Empresa del ERP
     * @param int                 $typeId  Tipo en CM: 2=Casa Software, 3=Servidor, 4=Partner
     */
    public function syncAccount($user, $company, int $typeId = 3): array
    {
        $payload = json_encode([
            'user' => [
                'email'         => $user->email,
                'password_hash' => $user->password,   // Bcrypt directo del ERP
                'first_name'    => $user->first_name ?? $user->name,
                'last_name'     => $user->last_name ?? '',
                'type_id'       => $typeId,
            ],
            'company' => [
                'company_name' => $company->name ?? $company->company_name,
                'dni'          => $company->nit ?? $company->dni,
                'dv'           => $company->dv ?? null,
                'email'        => $company->email ?? $user->email,
                'phone'        => $company->phone ?? null,
                'address'      => $company->address ?? null,
                'city_id'      => $company->city_id ?? null,
                'country_id'   => 45,  // Colombia
            ],
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', $payload . $timestamp, $this->secret);

        $response = Http::withHeaders([
            'Content-Type'     => 'application/json',
            'X-Sync-Key'       => $this->apiKey,
            'X-Sync-Signature' => $signature,
            'X-Sync-Timestamp' => (string) $timestamp,
        ])->withBody($payload, 'application/json')->post($this->url);

        if ($response->failed()) {
            Log::error('[CM-SYNC] Falló sincronización.', [
                'status' => $response->status(),
                'body'   => $response->json(),
                'email'  => $user->email,
            ]);
        }

        return $response->json();
    }
}
```

### 3. Configuración en `config/services.php` del ERP

```php
'cm' => [
    'sync_url'     => env('CM_SYNC_URL'),
    'sync_api_key' => env('CM_SYNC_API_KEY'),
    'sync_secret'  => env('CM_SYNC_SECRET'),
],
```

### 4. Uso

```php
// Después de registrar un usuario en el ERP:
app(CertificateManagerSyncService::class)->syncAccount($user, $company, 3);

// O en un listener de evento:
// UserRegistered::class => [SyncWithCertificateManager::class]
```

---

## Referencia del Endpoint

### Headers

| Header | Valor |
|---|---|
| `Content-Type` | `application/json` |
| `X-Sync-Key` | API Key |
| `X-Sync-Signature` | `hash_hmac('sha256', $jsonBody . $timestamp, $secret)` |
| `X-Sync-Timestamp` | Unix timestamp (ventana: 5 min) |

### Body

```json
{
    "user": {
        "email": "usuario@empresa.com",
        "password_hash": "$2y$12$...",
        "first_name": "Juan",
        "last_name": "Pérez",
        "type_id": 3
    },
    "company": {
        "company_name": "Empresa S.A.S",
        "dni": "900123456",
        "dv": "7",
        "email": "contacto@empresa.com",
        "phone": "3001234567",
        "address": "Calle 100 #15-20",
        "city_id": 1,
        "country_id": 45
    }
}
```

### Respuestas

| Código | Significado |
|---|---|
| `201` | Cuenta creada (usuario nuevo) |
| `200` | Cuenta actualizada (usuario existía) |
| `401` | API Key / firma / timestamp inválido |
| `403` | IP no autorizada |
| `422` | Validación de datos fallida |

### Comportamiento

| Escenario | Acción |
|---|---|
| Email no existe | Crea usuario + marca email verificado |
| Email ya existe | Actualiza nombre, apellido, password, activa |
| DNI no existe | Crea empresa |
| DNI ya existe | Actualiza nombre, email, teléfono, dirección |
| Vínculo user↔company no existe | Crea en `business_users` |
| Vínculo ya existe | No hace nada |
