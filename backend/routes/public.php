<?php
    Route::controller('LocationController')->group(function () {
        Route::get('countries',                 'getCountries');
        Route::get('departments',               'getDepartments');
        Route::get('cities',                    'getCities');
    });
    Route::controller('MasterController')->group(function () {
        Route::get('identity-documents',        'getIdentityDocuments');
        Route::get('organization-type',         'getTypeOrganization');
        Route::get('entity-document-types',     'getEntityDocumentTypes');
    });

    // Términos y Condiciones — versión vigente (público, la consume el
    // formulario de solicitud para enviar terms_version_id)
    Route::get('terms/current', [\App\Http\Controllers\TermsController::class, 'current'])
        ->name('v1.terms.current');
