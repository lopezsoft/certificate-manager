<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Toda la API pública vive bajo /api/v1/. El prefijo /api/v2/ se conserva
| temporalmente como capa de compatibilidad (redirects 308) hasta el sunset
| documentado en docs/2026-05-19-15-00-PLAN-UNIFICACION-API-V1-Y-PROVEEDOR-AGNOSTICO-VIAFIRMA.md
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// ── API v1: única versión soportada ──────────────────────────────────────
Route::group(['prefix' => 'v1'], function () {

    // Public methods
    require_once __DIR__ . "/public.php";
    require_once __DIR__ . "/authentication.php";
    require_once __DIR__ . "/auth-api.php";

    // ── Pricing público (movido desde v2) ─────────────────────────────────
    Route::get('pricing', [\App\Quotas\Http\Controllers\PricingController::class, 'index'])
        ->name('v1.pricing');

    Route::group(['middleware' => 'auth:api'], function () {

        Route::apiResource('crud', 'TableCrudController');

        // CONSUME DOCUMENTS
        Route::group(['prefix' => 'consume'], function () {
            Route::controller('ConsumeController')->group(function () {
                Route::get('/{year}', 'readByYear');
                Route::get('/{year}/{month}', 'readByMonth');
            });
        });

        // SENT DOCUMENTS — Solicitudes de certificado
        Route::group(['prefix' => 'certificate-request'], function () {
            Route::controller('CertificateRequestController')->group(function () {
                Route::post('/', 'createCertificateRequest')->middleware(['throttle:certificate-create', 'validate.mime']);
                Route::get('/', 'getCertificateRequest');
                Route::get('/all', 'getAllCertificateRequest');
                Route::get('/{id}', 'getCertificateRequestById');
                Route::put('/{id}', 'updateCertificateRequest');
                Route::put('/{id}/status', 'updateCertificateRequestStatus');
                Route::delete('/{id}', 'deleteCertificateRequest');
            });

            Route::controller('CertificateRequestFilesController')->group(function () {
                Route::post('/{id}/files', 'createFile')->middleware(['throttle:file-upload', 'validate.mime']);
                Route::delete('/{id}/files/{fileId}', 'deleteFile');
            });

            // ── Emisión agnóstica (mail / viafirma / futuros) ─────────────
            Route::controller('Certificate\CertificateIssuanceController')->group(function () {
                Route::post('/{id}/issue', 'issue')
                    ->middleware('throttle:send-mail')
                    ->name('v1.certificate-request.issue');

                Route::get('/{id}/issuance', 'show')
                    ->name('v1.certificate-request.issuance.show');

                Route::get('/{id}/issuance/download', 'download')
                    ->name('v1.certificate-request.issuance.download');

                Route::get('/{id}/issuance/download/file', 'downloadFile')
                    ->name('v1.certificate-request.issuance.download.file');

            });
        });

        // Company
        Route::group(['prefix' => 'company'], function () {
            Route::controller('CompanyController')->group(function () {
                Route::get('/',            'read');
                Route::group(['prefix' => 'settings'], function () {
                    Route::get('/',         'getSetting');
                    Route::put('/',         'updateSetting');
                });
            });
        });
        // Profile
        Route::group(['prefix' => 'profile'], function () {
            Route::controller('AuthController')->group(function () {
                Route::get('/',     'user');
                Route::get('types', 'types');
                Route::put('/{id}', 'updateUser');
            });
        });
        Route::group(['prefix' => 'settings'], function () {
            Route::group(['prefix' => 'reports'], function () {
                Route::controller('ReportsHeaderController')->group(function () {
                    Route::get('/', 'getData');
                    Route::put('/{id}', 'update');
                });
            });
        });

        // Personal Access Tokens (PAT)
        Route::group(['prefix' => 'tokens'], function () {
            Route::controller(\App\Http\Controllers\Api\TokenController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store')->middleware('throttle:token-create');
                Route::post('/revoke-all', 'revokeAll');
                Route::get('/{id}', 'show');
                Route::delete('/{id}', 'destroy');
                Route::post('/{id}/renew', 'renew');
            });
        });

        // Notificaciones de vencimiento de certificados
        Route::group(['prefix' => 'certificates'], function () {
            Route::get('/expiring', [\App\Http\Controllers\NotificationController::class, 'expiring']);
        });

        Route::group(['prefix' => 'notifications'], function () {
            Route::get('/', [\App\Http\Controllers\NotificationController::class, 'index']);
            Route::post('/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead']);
            Route::post('/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead']);
        });

        Route::group(['prefix' => 'admin', 'middleware' => 'admin'], function () {
            Route::post('/certificates/notify-now', [\App\Http\Controllers\NotificationController::class, 'triggerNow'])
                ->middleware('throttle:1,5');
        });

        // Webhooks
        Route::group(['prefix' => 'webhooks'], function () {
            Route::get('/events', [\App\Webhooks\Http\Controllers\WebhookEndpointController::class, 'availableEvents']);
            Route::get('/', [\App\Webhooks\Http\Controllers\WebhookEndpointController::class, 'index']);
            Route::post('/', [\App\Webhooks\Http\Controllers\WebhookEndpointController::class, 'store']);
            Route::get('/{id}', [\App\Webhooks\Http\Controllers\WebhookEndpointController::class, 'show']);
            Route::put('/{id}', [\App\Webhooks\Http\Controllers\WebhookEndpointController::class, 'update']);
            Route::delete('/{id}', [\App\Webhooks\Http\Controllers\WebhookEndpointController::class, 'destroy']);
            Route::post('/{id}/rotate-secret', [\App\Webhooks\Http\Controllers\WebhookEndpointController::class, 'rotateSecret']);
            Route::get('/{id}/deliveries', [\App\Webhooks\Http\Controllers\WebhookDeliveryController::class, 'index']);
        });

        // ── Órdenes WOMPI (prepaid) — movidas desde v2 ────────────────────
        Route::prefix('orders')->name('v1.orders.')->group(function () {
            Route::get('/',          [\App\Quotas\Http\Controllers\OrderController::class, 'index'])->name('index');
            Route::post('/',         [\App\Quotas\Http\Controllers\OrderController::class, 'store'])->name('store');
            Route::get('/{id}',      [\App\Quotas\Http\Controllers\OrderController::class, 'show'])->name('show');
            Route::post('/{id}/pay', [\App\Quotas\Http\Controllers\OrderController::class, 'pay'])->name('pay');
        });

        // ── Admin: gestión de cuotas POSTPAID — movido desde v2 ───────────
        Route::prefix('admin/quotas')->name('v1.admin.quotas.')->group(function () {
            Route::get('/',             [\App\Quotas\Http\Controllers\QuotaController::class, 'index'])->name('index');
            Route::post('/',            [\App\Quotas\Http\Controllers\QuotaController::class, 'store'])->name('store');
            Route::get('/{id}',         [\App\Quotas\Http\Controllers\QuotaController::class, 'show'])->name('show');
            Route::get('/company/{id}', [\App\Quotas\Http\Controllers\QuotaController::class, 'byCompany'])->name('by-company');
        });

        // ── Analíticas IA — movido desde v2 ───────────────────────────────
        Route::prefix('analytics')->name('v1.analytics.')->group(function () {
            Route::get('/results',      [\App\Http\Controllers\DocumentAnalysisController::class, 'index'])->name('results');
            Route::get('/stats',        [\App\Http\Controllers\DocumentAnalysisController::class, 'stats'])->name('stats');
            Route::get('/providers',    [\App\Http\Controllers\DocumentAnalysisController::class, 'providers'])->name('providers');
            Route::get('/results/{id}', [\App\Http\Controllers\DocumentAnalysisController::class, 'show'])->name('results.show');
        });

        // ── Health check (admin) — movido desde v2 ────────────────────────
        Route::get('health', \App\Http\Controllers\System\HealthCheckController::class)
            ->name('v1.health');
    });
});

// ── Webhooks externos (WOMPI) — sin auth:api, firma HMAC ─────────────────
require __DIR__ . '/webhooks-external.php';

