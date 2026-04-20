<?php

namespace App\Andes\Services;

use App\Andes\Exceptions\AndesAuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AndesTokenManager
 *
 * Gestiona el token OAuth2 de ANDES ID API con caché automático.
 * El token expira en 1h; lo cacheamos 55 min para renovar con margen.
 *
 * Seguridad: el token NO se loguea nunca (datos sensibles).
 */
class AndesTokenManager
{
    private const CACHE_KEY = 'andes_id_oauth_token';

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly int    $cacheTtl,
    ) {}

    /**
     * Devuelve un token válido. Lo recupera del caché o lo renueva.
     *
     * @throws AndesAuthenticationException si no se puede autenticar
     */
    public function getValidToken(): string
    {
        return Cache::remember(
            self::CACHE_KEY,
            $this->cacheTtl,
            fn () => $this->authenticate()
        );
    }

    /**
     * Fuerza la renovación del token (invalida caché y re-autentica).
     *
     * @throws AndesAuthenticationException
     */
    public function refreshToken(): string
    {
        Cache::forget(self::CACHE_KEY);
        return $this->getValidToken();
    }

    /**
     * Realiza el POST /login a ANDES ID API y devuelve el access_token.
     *
     * @throws AndesAuthenticationException
     */
    private function authenticate(): string
    {
        Log::info('[ANDES] Renovando token OAuth2 de ANDES ID API.');

        $response = Http::timeout(15)
            ->post("{$this->apiUrl}/login", [
                'username' => $this->username,
                'password' => $this->password,
            ]);

        if (! $response->successful()) {
            Log::error('[ANDES] Fallo de autenticación con ANDES ID.', [
                'status' => $response->status(),
            ]);
            throw new AndesAuthenticationException(
                "No se pudo autenticar con ANDES ID. HTTP {$response->status()}"
            );
        }

        $data  = $response->json();
        $token = $data['access_token'] ?? $data['token'] ?? null;

        if (! $token) {
            throw new AndesAuthenticationException(
                'ANDES ID no devolvió access_token en la respuesta de login.'
            );
        }

        Log::info('[ANDES] Token OAuth2 renovado correctamente.');

        return $token;
    }
}

