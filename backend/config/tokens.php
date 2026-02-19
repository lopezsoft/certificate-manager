<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Personal Access Tokens (PAT)
    |--------------------------------------------------------------------------
    |
    | Configuración del sistema de tokens de acceso personal para integraciones
    | externas (ERPs, scripts, webhooks entrantes, CLIs).
    |
    */

    // Días de validez por defecto al crear un PAT
    'expiration_days' => (int) env('PAT_EXPIRATION_DAYS', 90),

    // Límite máximo de días que puede solicitar el cliente al crear un PAT
    'max_expiration_days' => (int) env('PAT_MAX_EXPIRATION_DAYS', 365),

    // Máximo de tokens nuevos permitidos por usuario por día (rate limiting)
    'max_per_day' => (int) env('PAT_MAX_PER_DAY', 10),

    // Máximo de tokens activos simultáneos por usuario
    'max_active' => (int) env('PAT_MAX_ACTIVE', 20),

];
