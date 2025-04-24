<?php
Route::group(['prefix' => 'auth'], function () {
        Route::controller('AuthController')->group(function () {
            Route::post('login', 'login');
            Route::group(['middleware' => 'auth:api'], function() {
                Route::get('logout', 'logout');
                Route::get('user', 'user');
            });
        });
    });
