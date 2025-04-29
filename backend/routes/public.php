<?php
    Route::controller('LocationController')->group(function () {
        Route::get('countries',                 'getCountries');
        Route::get('departments',               'getDepartments');
        Route::get('cities',                    'getCities');
    });
    Route::controller('MasterController')->group(function () {
        Route::get('identity-documents',        'getIdentityDocuments');
        Route::get('organization-type',         'getTypeOrganization');
    });

