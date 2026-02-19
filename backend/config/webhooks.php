<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cola de entrega de webhooks
    |--------------------------------------------------------------------------
    | Cola dedicada para no interferir con operaciones críticas.
    */
    'queue' => env('WEBHOOK_QUEUE', 'webhooks'),

    /*
    |--------------------------------------------------------------------------
    | Timeout en segundos para peticiones HTTP salientes
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('WEBHOOK_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Máximo de fallos consecutivos antes de auto-desactivar un endpoint
    |--------------------------------------------------------------------------
    */
    'max_failures' => (int) env('WEBHOOK_MAX_FAILURES', 10),

    /*
    |--------------------------------------------------------------------------
    | Máximo de endpoints por compañía
    |--------------------------------------------------------------------------
    */
    'max_endpoints_per_company' => (int) env('WEBHOOK_MAX_ENDPOINTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Retención de logs de entrega en días (para el comando cleanup)
    |--------------------------------------------------------------------------
    */
    'delivery_log_retention_days' => (int) env('WEBHOOK_LOG_RETENTION', 30),
];
