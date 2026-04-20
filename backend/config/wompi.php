<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WOMPI – Payment Gateway (Colombia)
    |--------------------------------------------------------------------------
    | Sandbox: https://sandbox.wompi.co/v1
    | Producción: https://production.wompi.co/v1
    */
    'api_url'       => env('WOMPI_API_URL', 'https://sandbox.wompi.co/v1'),
    'public_key'    => env('WOMPI_PUBLIC_KEY'),
    'private_key'   => env('WOMPI_PRIVATE_KEY'),

    /*
    | Clave para validar firma HMAC-SHA256 de eventos (webhooks)
    */
    'events_secret' => env('WOMPI_EVENTS_SECRET'),

    /*
    | Clave de integridad para generar hash de transacciones en widget
    */
    'integrity_key' => env('WOMPI_INTEGRITY_KEY'),

    'currency'       => env('WOMPI_CURRENCY', 'COP'),
    'tax_percentage' => (int) env('WOMPI_TAX_PERCENTAGE', 19),
];

