<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v2 Routes — ANDES SCD + WOMPI + Cupos
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

    // ── Solicitudes de certificado v2 (ANDES) ──
    Route::prefix('certificate-request')->name('v2.certificate-request.')->group(function () {
        Route::post('/', [\App\Http\Controllers\V2\CertificateRequestV2Controller::class, 'store'])
            ->name('store');
        Route::get('/{id}', [\App\Http\Controllers\V2\CertificateRequestV2Controller::class, 'show'])
            ->name('show');
    });

    // ── ANDES Identity Validation ──
    Route::prefix('andes/identity')->name('v2.andes.identity.')->group(function () {
        Route::post('start',            [\App\Http\Controllers\V2\AndesIdentityController::class, 'start'])
            ->name('start')
            ->middleware([\App\Andes\Http\Middleware\AndesRateLimiterMiddleware::class . ':start']);
        Route::post('verify-otp',       [\App\Http\Controllers\V2\AndesIdentityController::class, 'verifyOtp'])
            ->name('verify-otp')
            ->middleware([\App\Andes\Http\Middleware\AndesRateLimiterMiddleware::class . ':verify-otp']);
        Route::post('verify-questions', [\App\Http\Controllers\V2\AndesIdentityController::class, 'verifyQuestions'])
            ->name('verify-questions');
        Route::post('resend-otp',       [\App\Http\Controllers\V2\AndesIdentityController::class, 'resendOtp'])
            ->name('resend-otp')
            ->middleware([\App\Andes\Http\Middleware\AndesRateLimiterMiddleware::class . ':resend-otp']);
        Route::post('bypass',           [\App\Http\Controllers\V2\AndesIdentityController::class, 'bypassToQuestions'])
            ->name('bypass');
        Route::post('status',           [\App\Http\Controllers\V2\AndesIdentityController::class, 'checkStatus'])
            ->name('status');
    });

    // ── Órdenes de compra (WOMPI PREPAID) ──
    Route::prefix('orders')->name('v2.orders.')->group(function () {
        Route::get('/',         [\App\Quotas\Http\Controllers\OrderController::class, 'index'])->name('index');
        Route::post('/',        [\App\Quotas\Http\Controllers\OrderController::class, 'store'])->name('store');
        Route::get('/{id}',     [\App\Quotas\Http\Controllers\OrderController::class, 'show'])->name('show');
        Route::post('/{id}/pay',[\App\Quotas\Http\Controllers\OrderController::class, 'pay'])->name('pay');
    });

    // ── Admin: gestión de cupos POSTPAID (solo rol admin) ──
    Route::prefix('admin/quotas')->name('v2.admin.quotas.')->middleware(['auth:api'])->group(function () {
        Route::get('/',             [\App\Quotas\Http\Controllers\QuotaController::class, 'index'])->name('index');
        Route::post('/',            [\App\Quotas\Http\Controllers\QuotaController::class, 'store'])->name('store');
        Route::get('/{id}',         [\App\Quotas\Http\Controllers\QuotaController::class, 'show'])->name('show');
        Route::get('/company/{id}', [\App\Quotas\Http\Controllers\QuotaController::class, 'byCompany'])->name('by-company');
    });
});

