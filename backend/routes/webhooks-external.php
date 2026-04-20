<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooks Externos — WOMPI
|--------------------------------------------------------------------------
|
| Estos endpoints NO usan auth:api porque son llamados directamente
| por WOMPI desde sus servidores. La validación de autenticidad se
| realiza verificando la firma HMAC-SHA256 (middleware ValidateWompiSignature).
|
*/

Route::post(
    '/webhooks/wompi',
    [\App\Payments\Http\Controllers\WompiWebhookController::class, 'handle']
)->middleware([\App\Payments\Http\Middleware\ValidateWompiSignature::class])
 ->name('webhooks.wompi');

