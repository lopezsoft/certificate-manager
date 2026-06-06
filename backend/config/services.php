<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'aws' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'textract_region' => env('AWS_TEXTRACT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sincronización Inter-Sistema (ERP / API → Certificate Manager)
    |--------------------------------------------------------------------------
    |
    | Credenciales para autenticación servicio-a-servicio vía HMAC-SHA256.
    | Generar con: php -r "echo bin2hex(random_bytes(32));"
    |
    */
    'sync' => [
        'api_key'     => env('SYNC_API_KEY'),
        'api_secret'  => env('SYNC_API_SECRET'),
        'allowed_ips' => array_filter(explode(',', env('SYNC_ALLOWED_IPS', ''))),
    ],

];
