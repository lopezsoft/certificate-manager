<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
// UBL 2.1
Route::group(['prefix' => 'v1'], function () {

    // Public methods
    require_once __DIR__ . "/public.php";
    // end Public methods
    require_once __DIR__ . "/authentication.php";
    require_once __DIR__ . "/auth-api.php";
    Route::group(['middleware' => 'auth:api'], function () {

        Route::apiResource('crud', 'TableCrudController');
        // CONSUME DOCUMENTS
        Route::group(['prefix' => 'consume'], function () {
            Route::controller('ConsumeController')->group(function () {
                Route::get('/{year}', 'readByYear');
                Route::get('/{year}/{month}', 'readByMonth');
            });
        });
        // SENT DOCUMENTS
        Route::group(['prefix' => 'certificate-request'], function () {
            Route::controller('CertificateRequestController')->group(function () {
                Route::post('/', 'createCertificateRequest')->middleware(['throttle:certificate-create', 'validate.mime']);
                Route::post('/{id}/send-mail', 'sendMail')->middleware('throttle:send-mail');
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
    });
});
