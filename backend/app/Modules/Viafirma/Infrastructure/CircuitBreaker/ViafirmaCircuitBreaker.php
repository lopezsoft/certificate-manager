<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\CircuitBreaker;

use Illuminate\Support\Facades\Cache;

/**
 * Circuit Breaker para el cliente Viafirma (V-306).
 *
 * Patrón: Circuit Breaker (Michael Nygard, Release It!).
 * Implementación basada en Cache (Redis o file) — sin dependencias externas.
 *
 * Estados:
 *  - CLOSED: operación normal, se permite polling
 *  - OPEN: demasiados fallos consecutivos, se pausa polling por `recovery_seconds`
 *  - HALF_OPEN: un único intento de prueba tras la ventana de recuperación
 *
 * Configuración: config('viafirma.circuit_breaker')
 */
final class ViafirmaCircuitBreaker
{
    private const CACHE_PREFIX = 'viafirma:circuit_breaker:';

    private readonly int $threshold;
    private readonly int $recoverySeconds;
    private readonly string $cacheStore;

    public function __construct()
    {
        $config = (array) config('viafirma.circuit_breaker', []);
        $this->threshold       = (int) ($config['failure_threshold'] ?? 5);
        $this->recoverySeconds = (int) ($config['recovery_seconds'] ?? 300);
        $this->cacheStore      = (string) ($config['cache_store'] ?? config('cache.default', 'file'));
    }

    /**
     * ¿Está abierto el circuito? Si sí, el polling debe pausarse.
     */
    public function isOpen(): bool
    {
        return (bool) $this->cache()->get(self::CACHE_PREFIX . 'open', false);
    }

    /**
     * Registra un fallo (5xx / timeout).
     * Si se alcanza el threshold, abre el circuito.
     */
    public function recordFailure(): void
    {
        $key   = self::CACHE_PREFIX . 'failures';
        $count = (int) $this->cache()->get($key, 0) + 1;

        // TTL del contador = ventana de observación (2x recovery para no perder estado)
        $this->cache()->put($key, $count, $this->recoverySeconds * 2);

        if ($count >= $this->threshold) {
            $this->openCircuit();
        }
    }

    /**
     * Registra un éxito → resetea el contador y cierra el circuito.
     */
    public function recordSuccess(): void
    {
        $this->cache()->forget(self::CACHE_PREFIX . 'failures');
        $this->cache()->forget(self::CACHE_PREFIX . 'open');
    }

    /**
     * Cantidad de fallos acumulados (diagnóstico).
     */
    public function failureCount(): int
    {
        return (int) $this->cache()->get(self::CACHE_PREFIX . 'failures', 0);
    }

    private function openCircuit(): void
    {
        $this->cache()->put(
            self::CACHE_PREFIX . 'open',
            true,
            $this->recoverySeconds,
        );
    }

    /**
     * @return \Illuminate\Contracts\Cache\Repository
     */
    private function cache()
    {
        return Cache::store($this->cacheStore);
    }
}
