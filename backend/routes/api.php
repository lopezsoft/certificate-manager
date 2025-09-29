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
                Route::post('/', 'createCertificateRequest');
                Route::post('/{id}/send-mail', 'sendMail');
                Route::get('/', 'getCertificateRequest');
                Route::get('/all', 'getAllCertificateRequest');
                Route::get('/{id}', 'getCertificateRequestById');
                Route::put('/{id}', 'updateCertificateRequest');
                Route::put('/{id}/status', 'updateCertificateRequestStatus');
                Route::delete('/{id}', 'deleteCertificateRequest');
                // Files
            });
            Route::controller('CertificateRequestFilesController')->group(function () {
                Route::post('/{id}/files', 'createFile');
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
    });
});
