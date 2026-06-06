# Deploy — Sincronización de Cuentas Inter-Sistema

> **Fecha:** 2026-06-06  
> **Branch:** `feature/unify-v1-api-routes`  
> **Commit:** `feat(auth): add secure inter-system account sync endpoint with HMAC-SHA256`

---

## 1. Migraciones Pendientes

Ejecutar en orden:

```bash
# Renombrar columnas acopladas en payment_transactions
php artisan migrate --path=database/migrations/2026_06_05_181200_update_payment_transactions_to_generic_columns.php

# Agregar UUID a certificate_orders
php artisan migrate --path=database/migrations/2026_06_05_185300_add_uuid_to_certificate_orders_table.php
```

> [!WARNING]
> Verificar que el **trigger de UUID** esté activo en la tabla `certificate_orders` antes de crear nuevas órdenes.

---

## 2. Variables de Entorno

Agregar al `.env` de **producción**:

```env
# ── Sincronización Inter-Sistema ──────────────────────────────
SYNC_API_KEY=<generar>
SYNC_API_SECRET=<generar>
SYNC_ALLOWED_IPS=                # Opcional: IPs separadas por coma
```

### Generar credenciales seguras

```bash
# API Key (32 chars hex)
php -r "echo 'cm-sync-' . bin2hex(random_bytes(16)) . PHP_EOL;"

# API Secret (64 chars hex)
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

> [!CAUTION]
> Las mismas credenciales (`SYNC_API_KEY` y `SYNC_API_SECRET`) deben configurarse en los sistemas origen (ERP/API) que consumirán el endpoint.

---

## 3. Limpiar Cache

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

---

## 4. Reiniciar Queue Worker

```bash
php artisan queue:restart
```

---

## 5. Archivos Nuevos

| Archivo | Descripción |
|---|---|
| `app/Http/Middleware/ValidateSyncSignature.php` | Middleware HMAC-SHA256 para autenticación S2S |
| `database/migrations/2026_06_05_181200_update_payment_transactions_to_generic_columns.php` | Renombra columnas Wompi → genéricas |
| `database/migrations/2026_06_05_185300_add_uuid_to_certificate_orders_table.php` | Agrega UUID a órdenes |

## 6. Archivos Modificados

| Archivo | Cambio |
|---|---|
| `app/Http/Controllers/Auth/RegisteredUserController.php` | Nuevo método `syncAccount()` |
| `app/Http/Kernel.php` | Registra middleware `sync.signature` |
| `config/services.php` | Agrega config `sync` (api_key, api_secret, allowed_ips) |
| `routes/auth-api.php` | Ruta `POST /api/v1/sync-account` |
| `app/Services/PricingService.php` | Pricing con volumen histórico (user_type 3,4) |
| `app/Services/OrderService.php` | Pasa `companyId` a pricing |
| `app/Http/Controllers/PricingController.php` | Pasa `companyId` a pricing |
| `app/Http/Controllers/OrderController.php` | Rutas con UUID, cast int |
| `app/Models/CertificateOrder.php` | UUID como route key, `$hidden = ['id']` |
| `app/Payments/Models/PaymentTransaction.php` | Columnas genéricas |
| `app/Payments/Services/WompiPaymentService.php` | Firma dinámica con `signature.properties` |
| `app/Services/PaymentOrchestrator.php` | Amount en valor real, soporte widget |

---

## 7. Endpoint Nuevo

### `POST /api/v1/sync-account`

**Autenticación:** HMAC-SHA256 (NO Passport)

**Headers requeridos:**

| Header | Valor |
|---|---|
| `Content-Type` | `application/json` |
| `X-Sync-Key` | API Key configurada en `.env` |
| `X-Sync-Signature` | `HMAC-SHA256(body + timestamp, secret)` |
| `X-Sync-Timestamp` | Unix timestamp actual |

**Body:**

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

**Respuestas:**

| Código | Significado |
|---|---|
| `201` | Usuario + empresa creados |
| `200` | Usuario + empresa actualizados |
| `401` | API Key / firma / timestamp inválido |
| `422` | Validación de datos fallida |

---

## 8. Ejemplo de Integración (Sistema Origen)

```php
// En el ERP o API externa (Laravel)
$payload   = json_encode($data);
$timestamp = time();
$signature = hash_hmac('sha256', $payload . $timestamp, env('CM_SYNC_SECRET'));

$response = Http::withHeaders([
    'X-Sync-Key'       => env('CM_SYNC_API_KEY'),
    'X-Sync-Signature' => $signature,
    'X-Sync-Timestamp' => $timestamp,
    'Content-Type'     => 'application/json',
])->post('https://cm-api.produccion.com/api/v1/sync-account', $data);
```

---

## 9. Breaking Changes

> [!WARNING]
> **Órdenes ahora usan UUID en las rutas.** El frontend debe usar `uuid` en vez de `id`:
> ```
> GET    /api/v1/orders/{uuid}        (antes: {id})
> POST   /api/v1/orders/{uuid}/pay    (antes: {id})
> POST   /api/v1/orders/{uuid}/retry  (antes: {id})
> DELETE /api/v1/orders/{uuid}        (antes: {id})
> ```
> El campo `id` ya no se expone en las respuestas JSON de órdenes.

---

## 10. Checklist de Verificación

- [ ] Migraciones ejecutadas sin error
- [ ] Trigger de UUID activo en `certificate_orders`
- [ ] Variables `SYNC_API_KEY` y `SYNC_API_SECRET` en `.env`
- [ ] `config:clear` ejecutado
- [ ] Queue worker reiniciado
- [ ] Probar creación de orden (UUID en respuesta)
- [ ] Probar pago con Wompi (webhook actualiza estado)
- [ ] Probar `POST /sync-account` con firma válida → 201
- [ ] Probar `POST /sync-account` con firma inválida → 401
- [ ] Login con credenciales sincronizadas funciona
