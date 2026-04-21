<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ANDES ID – API REST (Verificación de Identidad)
    |--------------------------------------------------------------------------
    | Base URL: https://v2.andesid.com.co/api
    | Autenticación: OAuth2 Bearer Token (TTL 1h – se cachea 55min)
    */
    'id_api_url'  => env('ANDES_ID_API_URL', 'https://v2.andesid.com.co/api'),
    'id_username' => env('ANDES_ID_USERNAME', ''),
    'id_password' => env('ANDES_ID_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | ANDES PKI – WebService SOAP (Certificados Digitales)
    |--------------------------------------------------------------------------
    | Autenticación: WS-Security UsernameToken (PasswordDigest)
    | MVP: solo tipoCert 10 (P.Jurídica) y 11 (P.Natural) – FE
    */
    'pki_wsdl_url' => env('ANDES_PKI_WSDL_URL', 'https://ra.andesscd.com.co/test/WebService/wsdl.php'),
    'pki_username' => env('ANDES_PKI_USERNAME', ''),
    'pki_password' => env('ANDES_PKI_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Cache & Polling
    |--------------------------------------------------------------------------
    */
    'token_cache_ttl'      => (int) env('ANDES_TOKEN_TTL', 3300),      // 55 min
    'polling_interval'     => (int) env('ANDES_POLLING_INTERVAL', 3600), // 1 hora
    'polling_max_attempts' => (int) env('ANDES_POLLING_MAX', 48),        // 48h máx
];

