<?php
    Route::get('numbersToLetters/{number}', 'Api\FunctionsController@getNumbersToLetters');
    Route::get('dv/{number}',               'Api\FunctionsController@getDigitVerification');
    Route::controller('LocationController')->group(function () {
        Route::get('countries',                 'getCountries');
        Route::get('departments',               'getDepartments');
        Route::get('cities',                    'getCities');
    });
    Route::controller('TaxesController')->group(function () {
        Route::get('taxes',         'getTaxes');
        Route::get('tax-rates',     'getTaxRates');
        Route::get('fiscal-regime',     'getTaxLevel');
        Route::get('accounting-regime',    'getTaxRegime');
    });
    Route::controller('MasterController')->group(function () {
        Route::get('discount-codes',            'getDiscountCodes');
        Route::get('currency',                  'getCurrency');
        Route::get('correction-notes',          'getCorrectionNotes');
        Route::get('destination-environment',   'getDestinationEnvironment');
        Route::get('document-type',             'getDocumentType');
        Route::get('operation-type',            'getOperationType');
        Route::get('identity-documents',        'getIdentityDocuments');
        Route::get('organization-type',         'getTypeOrganization');
        Route::get('quantity-units',            'getQuantityUnits');
        Route::get('type-item-identifications', 'getTypeItemIdentifications');
        Route::get('reference-price',           'getReferencePrice');
        Route::get('payment-methods',           'getPaymentMethods');
        Route::get('payment-means',             'geMeansPayment');
        Route::get('currencies',                'getCurrencies');
        Route::prefix('health')->group(function () {
            Route::get('user-type', 		'getHealthUserType');
            Route::get('contracting', 		'getHealthContracting');
            Route::get('coverage', 		    'getHealthCoverage');
        });
    });

    // begign electronic payroll
    Route::prefix('ep')->group(function () {
        // public methods
        Route::controller('EpController')->group(function () {
            Route::get('adjustment-note-type', 			    'getAdjustmentNoteType');
            Route::get('contract-type', 				    'getContractType');
            Route::get('disability-type', 					'getDisabilityType');
            Route::get('extra-hours', 						'getExtraHours');
            Route::get('payroll-period', 					'getPayrollPeriod');
            Route::get('worker-subtype', 					'getWorkerSubtype');
            Route::get('worker-type', 						'getWorkerType');
        });
    });
    // end electronic payroll
