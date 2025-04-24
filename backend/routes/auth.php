<?php

use Illuminate\Support\Facades\Route;


Route::post('/login', function () {
    return view('/');
})->middleware('guest')->name('login');
