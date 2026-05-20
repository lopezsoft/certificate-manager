<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v2 (Deprecated) — Redirects 308 hacia /api/v1/*
|--------------------------------------------------------------------------
|
| Estas rutas existen sólo durante el periodo de _sunset_ del prefijo v2
| introducido por error en sprints anteriores. Devuelven Redirect 308
| (Permanent Redirect preservando método y body) hacia la ubicación
| canónica bajo /api/v1/.
|
| Excepción: las rutas `/certificates/viafirma/*` NO se redirigen — su
| superficie pública fue retirada y cualquier llamante recibe 410 Gone
| con la URL sucesora en el header Link.
|
| Eliminación planificada: Fase 3 del plan
| (docs/2026-05-19-15-00-PLAN-UNIFICACION-API-V1-Y-PROVEEDOR-AGNOSTICO-VIAFIRMA.md).
|
*/

// ── Redirects 308 hacia v1 ────────────────────────────────────────────────
Route::redirect('pricing', '/api/v1/pricing', 308);

Route::redirect('orders',          '/api/v1/orders',          308);
Route::redirect('orders/{any}',    '/api/v1/orders/{any}',    308)->where('any', '.*');

Route::redirect('admin/quotas',          '/api/v1/admin/quotas',          308);
Route::redirect('admin/quotas/{any}',    '/api/v1/admin/quotas/{any}',    308)->where('any', '.*');

Route::redirect('analytics/{any}', '/api/v1/analytics/{any}', 308)->where('any', '.*');

Route::redirect('health', '/api/v1/health', 308);

// ── /certificates/viafirma/* → 410 Gone ──────────────────────────────────
Route::any('certificates/viafirma/{any?}', function () {
    return response()->json([
        'success' => false,
        'message' => "El endpoint /api/v2/certificates/viafirma fue retirado. Use /api/v1/certificate-request/{id}/issue (POST) o /api/v1/certificate-request/{id}/issuance (GET).",
        'code'    => 'ENDPOINT_REMOVED',
    ], 410)
        ->header('Link', '</api/v1/certificate-request/{id}/issue>; rel="successor-version"')
        ->header('Sunset', 'Wed, 02 Jul 2026 00:00:00 GMT');
})->where('any', '.*');

