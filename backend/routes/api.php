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

    Route::group(['middleware' => 'auth:api'], function () {

        // ── Pricing ───────────────────────────────────────────────
        Route::get('pricing', [\App\Http\Controllers\PricingController::class, 'index'])
            ->name('v1.pricing');

        // ── Estado de cupos (usuario autenticado) ────────────────
        Route::get('quota/status', [\App\Http\Controllers\QuotaStatusController::class, '__invoke'])
            ->name('v1.quota.status');

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
                Route::get('/lookup/{dni}', 'lookupByDni');
                Route::get('/stats/{companyId}', 'getStatsByCompany');
                Route::get('/{id}', 'getCertificateRequestById');
                Route::get('/{id}/history', 'getCertificateRequestHistory');
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
                    ->middleware('throttle:certificate-issue')
                    ->name('v1.certificate-request.issue');

                Route::get('/{id}/issuance', 'show')
                    ->name('v1.certificate-request.issuance.show');

                Route::get('/{id}/issuance/download', 'download')
                    ->name('v1.certificate-request.issuance.download');

                Route::get('/{id}/issuance/download/file', 'downloadFile')
                    ->name('v1.certificate-request.issuance.download.file');

                Route::get('/{id}/issuance/download/base64', 'downloadBase64')
                    ->name('v1.certificate-request.issuance.download.base64');

                Route::post('/{id}/issuance/redownload', 'redownload')
                    ->name('v1.certificate-request.issuance.redownload');

                Route::post('/{id}/issuance/renew', 'renew')
                    ->name('v1.certificate-request.issuance.renew');

            });

            // ── Viafirma: Revocación y KYC ────────────────────────────
            Route::post('/{id}/revoke', [
                \App\Modules\Viafirma\Presentation\Http\Controllers\RevocationController::class, 'revoke',
            ])->name('v1.certificate-request.revoke');

            Route::get('/{id}/kyc-link', [
                \App\Modules\Viafirma\Presentation\Http\Controllers\KycLinkController::class, 'show',
            ])->name('v1.certificate-request.kyc-link');

        });

        // Company
        Route::group(['prefix' => 'company'], function () {
            Route::controller('CompanyController')->group(function () {
                Route::get('/',            'read');
                Route::put('/{id}',        'update');
                Route::group(['prefix' => 'settings'], function () {
                    Route::get('/',         'getSetting');
                    Route::put('/',         'updateSetting');
                });
                // Solo administradores
                Route::patch('/{id}/toggle-active', 'toggleActive')->middleware('admin');
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
            Route::controller('Api\TokenController')->group(function () {
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
            Route::controller('NotificationController')->group(function () {
                Route::get('/expiring', 'expiring');
            });
        });

        Route::group(['prefix' => 'notifications'], function () {
            Route::controller('NotificationController')->group(function () {
                Route::get('/', 'index');
                Route::post('/read-all', 'markAllAsRead');
                Route::post('/{id}/read', 'markAsRead');
            });
        });

        Route::group(['prefix' => 'admin', 'middleware' => 'admin'], function () {
            Route::controller('NotificationController')->group(function () {
                Route::post('/certificates/notify-now', 'triggerNow')
                    ->middleware('throttle:1,5');
                Route::get('/certificates/expiring-by-company', 'expiringByCompany');
            });
        });

        // Webhooks
        Route::group(['prefix' => 'webhooks'], function () {
            Route::controller('\\' . \App\Webhooks\Http\Controllers\WebhookEndpointController::class)->group(function () {
                Route::get('/events', 'availableEvents');
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show');
                Route::put('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
                Route::post('/{id}/rotate-secret', 'rotateSecret');
            });

            Route::controller('\\' . \App\Webhooks\Http\Controllers\WebhookDeliveryController::class)->group(function () {
                Route::get('/{id}/deliveries', 'index');
            });
        });

        // ── Órdenes WOMPI (prepaid) — movidas desde v2 ────────────────────
        Route::prefix('orders')->name('v1.orders.')->group(function () {
            Route::controller('OrderController')->group(function () {
                Route::get('/',             'index')->name('index');
                Route::post('/',            'store')->name('store');
                Route::get('/{uuid}',       'show')->name('show');
                Route::post('/{uuid}/pay',  'pay')->name('pay');
                Route::post('/{uuid}/retry', 'retry')->name('retry');
                Route::delete('/{uuid}',    'destroy')->name('destroy');
            });
        });

        // ── Admin: gestión de cuotas POSTPAID — solo administradores ──────
        Route::prefix('admin/quotas')->middleware('admin')->name('v1.admin.quotas.')->group(function () {
            Route::controller('QuotaController')->group(function () {
                Route::get('/',             'index')->name('index');
                Route::post('/',            'store')->name('store');
                Route::get('/{id}',         'show')->name('show');
                Route::get('/company/{id}', 'byCompany')->name('by-company');
            });
        });

        // ── Analíticas IA — movido desde v2 ───────────────────────────────
        Route::prefix('analytics')->name('v1.analytics.')->group(function () {
            Route::controller('DocumentAnalysisController')->group(function () {
                Route::get('/results',      'index')->name('results');
                Route::get('/stats',        'stats')->name('stats');
                Route::get('/providers',    'providers')->name('providers');
                Route::get('/results/{id}', 'show')->name('results.show');
            });
        });

        // ── Health check (admin) — movido desde v2 ────────────────────────
        Route::controller('System\HealthCheckController')->group(function () {
            Route::get('health', '__invoke')->name('v1.health');
        });
    });
});

// ── Webhooks externos (WOMPI) — sin auth:api, firma HMAC ─────────────────
require __DIR__ . '/webhooks-external.php';

