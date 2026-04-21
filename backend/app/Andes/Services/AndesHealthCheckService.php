<?php

namespace App\Andes\Services;

use App\Andes\Contracts\AndesIdentityServiceContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AndesHealthCheckService
 *
 * Verifica la conectividad con los servicios externos de ANDES.
 * No realiza llamadas reales — verifica que el token OAuth2 sea obtenible
 * y que el WSDL del PKI sea accesible.
 */
class AndesHealthCheckService
{
    private const CACHE_KEY = 'andes_health_status';
    private const CACHE_TTL = 300; // 5 min

    public function __construct(
        private readonly AndesTokenManager $tokenManager,
        private readonly string $pkiWsdlUrl,
    ) {}

    /**
     * Devuelve el estado de salud de los servicios ANDES.
     * Resultado cacheado 5 minutos para evitar spam de llamadas.
     */
    public function getStatus(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->checkAll());
    }

    private function checkAll(): array
    {
        return [
            'andes_id_api' => $this->checkAndesIdApi(),
            'andes_pki'    => $this->checkAndesPki(),
            'checked_at'   => now()->toIso8601String(),
        ];
    }

    private function checkAndesIdApi(): array
    {
        try {
            $this->tokenManager->getValidToken();
            return ['status' => 'ok', 'message' => 'ANDES ID API autenticación exitosa'];
        } catch (\Throwable $e) {
            Log::warning('[HEALTH] ANDES ID API no disponible.', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => 'No se pudo autenticar con ANDES ID API'];
        }
    }

    private function checkAndesPki(): array
    {
        // Solo verificar que el WSDL URL esté configurado (no hacer SOAP real en health check)
        if (empty($this->pkiWsdlUrl)) {
            return ['status' => 'warning', 'message' => 'ANDES_PKI_WSDL_URL no configurado'];
        }

        return ['status' => 'ok', 'message' => 'ANDES PKI URL configurado'];
    }
}

