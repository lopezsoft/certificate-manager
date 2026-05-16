<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v2 Routes — WOMPI + Cuotas
|--------------------------------------------------------------------------
|
| Todos los endpoints aquí se montan bajo el prefijo /api/v2/
| que se configura en routes/api.php al incluir este archivo.
|
*/

// ── Endpoint público de tarifas (sin autenticación) ──────────────────────
Route::get('pricing', [\App\Quotas\Http\Controllers\PricingController::class, 'index'])
    ->name('v2.pricing');

// ── Rutas protegidas (requieren auth:api) ─────────────────────────────────
Route::middleware(['auth:api'])->group(function () {

    // ── Órdenes de compra (WOMPI PREPAID) ──
    Route::prefix('orders')->name('v2.orders.')->group(function () {
        Route::get('/',         [\App\Quotas\Http\Controllers\OrderController::class, 'index'])->name('index');
        Route::post('/',        [\App\Quotas\Http\Controllers\OrderController::class, 'store'])->name('store');
        Route::get('/{id}',     [\App\Quotas\Http\Controllers\OrderController::class, 'show'])->name('show');
        Route::post('/{id}/pay',[\App\Quotas\Http\Controllers\OrderController::class, 'pay'])->name('pay');
    });

    // ── Admin: gestión de cupos POSTPAID (solo rol admin) ──
    Route::prefix('admin/quotas')->name('v2.admin.quotas.')->group(function () {
        Route::get('/',             [\App\Quotas\Http\Controllers\QuotaController::class, 'index'])->name('index');
        Route::post('/',            [\App\Quotas\Http\Controllers\QuotaController::class, 'store'])->name('store');
        Route::get('/{id}',         [\App\Quotas\Http\Controllers\QuotaController::class, 'show'])->name('show');
        Route::get('/company/{id}', [\App\Quotas\Http\Controllers\QuotaController::class, 'byCompany'])->name('by-company');
    });

    // ── Analíticas IA (Sprint 4) ──
    Route::prefix('analytics')->name('v2.analytics.')->group(function () {
        Route::get('/results',      [\App\Http\Controllers\DocumentAnalysisController::class, 'index'])->name('results');
        Route::get('/stats',        [\App\Http\Controllers\DocumentAnalysisController::class, 'stats'])->name('stats');
        Route::get('/providers',    [\App\Http\Controllers\DocumentAnalysisController::class, 'providers'])->name('providers');
        Route::get('/results/{id}', [\App\Http\Controllers\DocumentAnalysisController::class, 'show'])->name('results.show');
    });

    // ── Health check (admin) ──
    Route::get('health', \App\Http\Controllers\V2\HealthCheckController::class)
        ->name('v2.health');

    // ── Viafirma Certificados (PKCS#10 Zero-Touch) ──
    Route::prefix('certificates/viafirma')
        ->name('v2.viafirma.')
        ->middleware(\App\Modules\Viafirma\Presentation\Http\Middleware\ViafirmaFeatureGate::class)
        ->group(function () {
        Route::post('/issue', [\App\Modules\Viafirma\Presentation\Http\Controllers\ViafirmaCertificateController::class, 'issue'])->name('issue');
        Route::get('/',       [\App\Modules\Viafirma\Presentation\Http\Controllers\ViafirmaCertificateController::class, 'index'])->name('index');
        Route::get('/{id}',   [\App\Modules\Viafirma\Presentation\Http\Controllers\ViafirmaCertificateController::class, 'show'])->name('show')->where('id', '[0-9]+');

        // Sprint 4: Descarga P12
        Route::get('/{id}/download',      [\App\Modules\Viafirma\Presentation\Http\Controllers\ViafirmaCertificateController::class, 'download'])->name('download')->where('id', '[0-9]+');
        Route::get('/{id}/download/file', [\App\Modules\Viafirma\Presentation\Http\Controllers\ViafirmaCertificateController::class, 'downloadFile'])->name('download.file')->where('id', '[0-9]+');
    });
});
