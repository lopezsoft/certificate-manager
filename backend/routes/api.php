<?php

use App\Http\Controllers\Mail\SNSNotificationController;
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
Route::group(['prefix' => 'ubl2.1'], function () {

    Route::post('/sns-notifications', [SNSNotificationController::class, 'handle']);
    // Public methods
    require_once __DIR__."/public.php";
    // end Public methods
    require_once __DIR__ . "/authentication.php";
    require_once __DIR__ . "/auth-api.php";
    Route::group(['middleware' => 'auth:api'], function() {

        Route::apiResource('crud', 'TableCrudController');
        // GetAcquirer
        Route::group(['prefix' => 'acquirer'], function () {
            Route::controller('Api\AcquirerController')->group(function () {
                Route::get('/', 'getAcquirer');
            });
        });
        /**
         * email-logs routes
         */
        Route::group(['prefix' => 'email-logs'], function () {
            Route::controller('Mail\EmailLogsController')->group(function () {
                Route::get('/', 'findAll');
                Route::get('{id}', 'findOne');
                Route::get('document/{document_id}', 'findByDocumentId');
            });
        });

        Route::group(['prefix' => 'events'], function () {
            Route::controller('Documents\EventsController')->group(function () {
                Route::get('document-receptions', 'getDocumentReceptions');
                Route::get('status/{trackId}','getEventStatus');
                Route::get('document-receptions/{documentId}','getEventSById');
                Route::post('import-excel','importExcel');
                Route::post('import-track-id','importTrackId');
                Route::post('{trackId}/import','importByTrackId');
                Route::post('send/{trackId}','sendEvent');
                Route::post('send/mail/{trackId}','sendEventMail');
            });
        });

        Route::controller('Api\ElectronicDocumentsController')->group(function () {
             // begin electronic payroll
            Route::prefix('ep')->group(function () {
                Route::prefix('payroll')->group(function () {
                    Route::post('/',        'payroll');
                    Route::post('replace',  'payrollReplace');
                    Route::post('delete',   'payrollDelete');
                });
            }); // end electronic payroll
            // Invoice
            Route::prefix('invoice')->group(function () {
                Route::post('/',                    'invoice');
            });
            //Document Support
            Route::prefix('ds')->group(function () {
                Route::post('document', 'documentSupport');
                Route::post('adjustment-note','adjustmentNote');
            });
            Route::prefix('document')->group(function () {
                Route::post('run-test', 'runTest');
            });
            Route::prefix('notes')->group(function (){
                Route::post('credit',   'note');
                Route::post('debit',    'note');
            });
        });
        // CURRENCY
        Route::group(['prefix' => 'currency'], function () {
            Route::controller('CurrencyController')->group(function () {
                Route::post('/',       'create');
                Route::get('/',          'read');
                Route::get('/all',        'all');
                Route::put('/{id}',   'update');
                Route::delete('/{id}','delete');
                Route::get('change',        'getChange');
                Route::get('change/local',  'getChangeLocal');
            });
        });
        Route::controller('Api\StateController')->group(function () {
            // GetNumberingRange
            Route::get('numbering-range', 'range');
            Route::get('exchange-emails', 'getExchangeEmails');

            // Status
            Route::group(['prefix' => 'status'], function () {
                Route::get('/', 'documentStatus');
                Route::post('zip/{trackId}', 'zip');
                Route::post('document/{trackId}', 'document');
                Route::post('document/test/{trackId}', 'documentTest');
            });
        });
        // SENT DOCUMENTS
        Route::group(['prefix' => 'documents'], function () {
            Route::controller('Documents\SentDocumentsController')->group(function () {
                Route::get('/',         'getDocuments');
                Route::get('last',      'getLastDocument');
                Route::get('consume',   'getConsume');
                Route::delete('/{id}',  'delete');
            });
            Route::controller('Documents\DocumentsController')->group(function () {
                Route::post('pdf/{trackId}',        'getPdf');
                Route::get('xml/{trackId}',         'getXmlRequest');
                Route::post('xml/{trackId}',         'getXmlRequest');
                Route::post('attached/{trackId}',   'getAttached');
                Route::post('sendmail/to',          'sendMailDocuments');
                Route::post('sendmail/{trackId}',   'sendMail');
            });
        });

        // Company
        Route::group(['prefix' => 'company'], function () {
            Route::controller('CompanyController')->group(function () {
                Route::get('/',            'read');
                Route::get('customers',    'customers');
                Route::group(['prefix' => 'settings'], function () {
                    Route::get('/',         'getSetting');
                    Route::put('/',         'updateSetting');
                });
                Route::put('/{id}',  'update');
                Route::delete('customer/{id}', 'deleteCustomer');
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
        // Resolutions
        Route::group(['prefix' => 'resolutions'], function () {
            Route::controller('ResolutionsController')->group(function () {
                Route::post('/',        'create');
                Route::get('/',         'getResolutions');
                Route::put('/{id}',     'update');
                Route::delete('/{id}',  'delete');
            });
        });
        // Software
        Route::group(['prefix' => 'software'], function () {
            Route::controller('SoftwareController')->group(function () {
                Route::post('/',        'create');
                Route::get('/',         'getSoftware');
                Route::get('test/{id}', 'getTestSoftware');
                Route::get('process/{id}', 'getProcessSoftware');
                Route::put('/{id}',     'update');
                Route::delete('/{id}',  'delete');
            });
        });
        // Certificate
        Route::group(['prefix' => 'certificate'], function () {
            Route::controller('CertificateController')->group(function () {
                Route::post('/',                'create');
                Route::get('/',                 'getCertificate');
                Route::get('/expiration/{dni}', 'getExpiration');
                Route::put('/{id}',             'update');
                Route::delete('/{id}',          'delete');
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
