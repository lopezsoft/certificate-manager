# Sincronización de Cuentas — `POST /api/v1/sync-account`

> **Fecha:** 2026-06-06  
> Este endpoint permite sincronizar cuentas de usuario desde sistemas externos (ERP, API) hacia Certificate Manager.  
> Como todos los sistemas usan Laravel (bcrypt), el password se transfiere directamente y el usuario ingresa con las mismas credenciales.

---

## Paso 1 — Generar credenciales compartidas

Ejecutar en cualquier terminal con PHP:

```bash
php -r "echo 'cm-sync-' . bin2hex(random_bytes(16)) . PHP_EOL;"   # → API Key
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"                  # → Secret
```

Ejemplo de resultado:

```
cm-sync-a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6
9f8e7d6c5b4a39281706f5e4d3c2b1a09f8e7d6c5b4a39281706f5e4d3c2b1a0
```

> ⚠️ Guardar ambos valores. Se usan en los dos sistemas.

---

## Paso 2 — Configurar Certificate Manager (destino)

Agregar al `.env` del CM:

```env
SYNC_API_KEY=cm-sync-a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6
SYNC_API_SECRET=9f8e7d6c5b4a39281706f5e4d3c2b1a09f8e7d6c5b4a39281706f5e4d3c2b1a0
SYNC_ALLOWED_IPS=                # Opcional: IPs del servidor origen separadas por coma
```

Limpiar cache:

```bash
php artisan config:clear
php artisan route:clear
```

✅ Listo. El CM ya acepta peticiones firmadas.

---

## Paso 3 — Configurar el Sistema Origen (ERP / API)

### 3.1 Variables de entorno

Agregar al `.env` del ERP:

```env
CM_SYNC_API_KEY=cm-sync-a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6
CM_SYNC_SECRET=9f8e7d6c5b4a39281706f5e4d3c2b1a09f8e7d6c5b4a39281706f5e4d3c2b1a0
CM_SYNC_URL=https://cm-api.tudominio.com/api/v1/sync-account
```

### 3.2 Agregar config

En `config/services.php` del ERP:

```php
'cm' => [
    'sync_url'     => env('CM_SYNC_URL'),
    'sync_api_key' => env('CM_SYNC_API_KEY'),
    'sync_secret'  => env('CM_SYNC_SECRET'),
],
```

### 3.3 Crear el servicio

Crear archivo `app/Services/CertificateManagerSyncService.php`:

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
     * @param  object $user    Usuario del ERP (necesita: email, password, first_name, last_name)
     * @param  object $company Empresa del ERP (necesita: company_name/name, dni/nit)
     * @param  int    $typeId  Tipo en CM: 2=Casa Software, 3=Arrendamiento Servidor, 4=Partner
     * @return array           Respuesta del CM
     */
    public function sync($user, $company, int $typeId = 3): array
    {
        // 1. Armar el payload
        $data = [
            'user' => [
                'email'         => $user->email,
                'password_hash' => $user->password,   // ← Se envía el hash bcrypt directo
                'first_name'    => $user->first_name ?? $user->name,
                'last_name'     => $user->last_name ?? '',
                'type_id'       => $typeId,
            ],
            'company' => [
                'company_name' => $company->company_name ?? $company->name,
                'dni'          => $company->dni ?? $company->nit,
                'dv'           => $company->dv ?? null,
                'email'        => $company->email ?? $user->email,
                'phone'        => $company->phone ?? null,
                'address'      => $company->address ?? null,
                'city_id'      => $company->city_id ?? null,
                'country_id'   => 45,
            ],
        ];

        $payload = json_encode($data);

        // 2. Firmar con HMAC-SHA256
        $timestamp = time();
        $signature = hash_hmac('sha256', $payload . $timestamp, $this->secret);

        // 3. Enviar
        $response = Http::withHeaders([
            'Content-Type'     => 'application/json',
            'X-Sync-Key'       => $this->apiKey,
            'X-Sync-Signature' => $signature,
            'X-Sync-Timestamp' => (string) $timestamp,
        ])->withBody($payload, 'application/json')->post($this->url);

        // 4. Log resultado
        if ($response->successful()) {
            Log::info('[CM-SYNC] Cuenta sincronizada.', [
                'email'  => $user->email,
                'action' => $response->json('data.user_action'),
            ]);
        } else {
            Log::error('[CM-SYNC] Error en sincronización.', [
                'email'  => $user->email,
                'status' => $response->status(),
                'error'  => $response->json(),
            ]);
        }

        return $response->json() ?? [];
    }
}
```

### 3.4 Usar el servicio

**Opción A — Llamada directa** (después de registrar un usuario):

```php
use App\Services\CertificateManagerSyncService;

// En el controlador de registro del ERP:
public function register(Request $request)
{
    $user    = User::create([...]);
    $company = Company::create([...]);

    // Sincronizar con Certificate Manager
    app(CertificateManagerSyncService::class)->sync($user, $company, 3);

    return response()->json(['message' => 'Registrado']);
}
```

**Opción B — Con evento/listener** (recomendada):

```php
// En EventServiceProvider del ERP:
protected $listen = [
    \App\Events\UserRegistered::class => [
        \App\Listeners\SyncWithCertificateManager::class,
    ],
];

// Listener:
class SyncWithCertificateManager
{
    public function handle(UserRegistered $event): void
    {
        app(CertificateManagerSyncService::class)
            ->sync($event->user, $event->company, 3);
    }
}
```

**Opción C — Artisan command** (sincronización masiva):

```php
// Para migrar usuarios existentes del ERP al CM:
User::with('company')->chunk(50, function ($users) {
    $sync = app(CertificateManagerSyncService::class);
    foreach ($users as $user) {
        $sync->sync($user, $user->company, 3);
        sleep(1); // Evitar saturar el CM
    }
});
```

---

## Referencia rápida

### ¿Cómo se firma la petición?

```
firma = HMAC-SHA256( json_body + unix_timestamp, secret )
```

El CM verifica que:
1. El `X-Sync-Key` coincida con su `.env`
2. El `X-Sync-Timestamp` no tenga más de 5 minutos de antigüedad
3. La firma `X-Sync-Signature` sea válida

### ¿Qué `type_id` usar?

| type_id | Tipo de usuario |
|---|---|
| `2` | Casa de Software |
| `3` | Arrendamiento en Servidor |
| `4` | Partner |

> ❌ `type_id = 1` (Administrador) está **bloqueado** por seguridad.

### Respuestas

| Código | Significado |
|---|---|
| `201` | Cuenta creada exitosamente |
| `200` | Cuenta actualizada exitosamente |
| `401` | Credenciales o firma inválida |
| `403` | IP no autorizada |
| `422` | Datos incompletos o inválidos |

### Comportamiento upsert

| Si... | Entonces... |
|---|---|
| El email **no existe** en CM | Crea usuario nuevo con el password del ERP |
| El email **ya existe** en CM | Actualiza nombre, apellido y password |
| El DNI **no existe** en CM | Crea empresa nueva |
| El DNI **ya existe** en CM | Actualiza datos de la empresa |
| El vínculo user↔empresa **no existe** | Lo crea automáticamente |
