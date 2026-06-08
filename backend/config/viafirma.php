<?php

/*
|--------------------------------------------------------------------------
| Viafirma RA Colombia — PKCS#10 Integration
|--------------------------------------------------------------------------
| ⚠️ Todos los dominios/URLs son CONFIGURABLES por entorno. NUNCA hardcodear.
| Variables requeridas en .env (sin defaults productivos — fail fast):
|   VIAFIRMA_RA_URL            base del API REST  (ej. https://<host>/ra/api/v2)
|   VIAFIRMA_RA_DOWNLOAD_URL   base para descarga del P7B (ej. https://<host>/ra)
|   VIAFIRMA_CLIENT_ID         OAuth1 Consumer Key
|   VIAFIRMA_CLIENT_SECRET     OAuth1 Consumer Secret
|   VIAFIRMA_RA_CODE           código de RA asignado por el proveedor
*/

return [

    'base_url'      => env('VIAFIRMA_RA_URL'),
    'download_url'  => env('VIAFIRMA_RA_DOWNLOAD_URL'),
    'client_id'     => env('VIAFIRMA_CLIENT_ID'),
    'client_secret' => env('VIAFIRMA_CLIENT_SECRET'),
    'ra_code'       => env('VIAFIRMA_RA_CODE'),
    'cod_profile'   => env('VIAFIRMA_COD_PROFILE'),

    // TTL (segundos) del caché de perfiles del RA en memoria local.
    // Los perfiles cambian rarísimo; cachearlos evita un HTTP GET en cada emisión.
    // Usar 0 para deshabilitar el caché (útil en desarrollo para ver cambios inmediatos).
    'profiles_cache_ttl_seconds' => (int) env('VIAFIRMA_PROFILES_CACHE_TTL', 3600),

    'timeout'       => (int) env('VIAFIRMA_HTTP_TIMEOUT', 30),
    'connect_timeout' => (int) env('VIAFIRMA_HTTP_CONNECT_TIMEOUT', 10),
    'retry'         => [
        'max'     => (int) env('VIAFIRMA_HTTP_RETRY_MAX', 3),
        'base_ms' => (int) env('VIAFIRMA_HTTP_RETRY_BASE_MS', 500),
    ],

    // Validez por defecto reportada por el perfil (V1.1 = 730 días).
    // Se sobrescribe con el campo `validity` retornado por /ra/available-profiles.
    'certificate_validity_days' => (int) env('VIAFIRMA_CERT_VALIDITY_DAYS', 730),

    'polling' => [
        'max_attempts'     => (int) env('VIAFIRMA_POLL_MAX_ATTEMPTS', 288), // 288 × 60s = 8h máximo
        'expiration_hours' => (int) env('VIAFIRMA_POLL_EXPIRATION_HOURS', 72),
        // Intervalo fijo entre polls (segundos). Máximo recomendado: 60.
        // Todos los estados usan este mismo valor; no hay backoff exponencial.
        'interval_seconds' => (int) env('VIAFIRMA_POLL_INTERVAL', 60),
    ],

    'crypto' => [
        'key_size'        => (int) env('VIAFIRMA_KEY_SIZE', 2048),
        'signature_algo'  => env('VIAFIRMA_SIG_ALGO', 'sha256WithRSAEncryption'),
        'digest_alg'      => env('VIAFIRMA_DIGEST_ALG', 'sha256'),
        // openssl.cnf empaquetado con la app — evita depender del openssl.cnf del SO
        // (frecuentemente ausente en WAMP/Windows). Es prerequisito para
        // openssl_pkey_new() y openssl_csr_new() en entornos sin OPENSSL_CONF.
        'openssl_conf'    => env('VIAFIRMA_OPENSSL_CONF', config_path('viafirma/openssl.cnf')),
        'key_vault_driver' => env('VIAFIRMA_KEY_VAULT_DRIVER', 'encrypted_local'), // encrypted_local | aws_kms
        'aws_kms_key_id'  => env('AWS_KMS_KEY_ID'),
        // Directorio relativo en el disco configurado para guardar material cifrado (driver local).
        'vault_disk'      => env('VIAFIRMA_VAULT_DISK', 'local'),
        'vault_path'      => env('VIAFIRMA_VAULT_PATH', 'viafirma/vault'),
    ],

    'storage' => [
        // Discos donde se guardan los artefactos descargados/ensamblados.
        'p7b_disk' => env('VIAFIRMA_P7B_DISK', 'local'),
        'p7b_path' => env('VIAFIRMA_P7B_PATH', 'viafirma/p7b'),
        'p12_disk' => env('VIAFIRMA_P12_DISK', 'local'),
        'p12_path' => env('VIAFIRMA_P12_PATH', 'viafirma/p12'),
    ],

    'circuit_breaker' => [
        'failure_threshold' => (int) env('VIAFIRMA_CB_FAILURE_THRESHOLD', 5),
        'recovery_seconds'  => (int) env('VIAFIRMA_CB_RECOVERY_SECONDS', 300),
        'cache_store'       => env('VIAFIRMA_CB_CACHE_STORE', env('CACHE_DRIVER', 'file')),
    ],

    'logging' => [
        // Canal Laravel logging. Si null usa el default; ver SafePemLogger.
        'channel' => env('VIAFIRMA_LOG_CHANNEL'),
    ],

    // Sprint 5: Feature flag + rollout gradual
    'feature_flag' => [
        'enabled'             => (bool) env('VIAFIRMA_PKCS10_ENABLED', true),
        'rollout_percentage'  => (int) env('VIAFIRMA_PKCS10_ROLLOUT_PCT', 100),
    ],
];



