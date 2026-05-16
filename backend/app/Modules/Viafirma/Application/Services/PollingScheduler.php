<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Services;

use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;

/**
 * Calculadora de intervalos de polling con exponential backoff + jitter (V-302).
 *
 * Fórmula (§4.2.1 del roadmap):
 *   nextDelay = base × min(2^floor(attempts/5), 8) + jitter(±jitter_pct%)
 *
 * Donde:
 *  - `base` = intervalo base por estado remoto (config/viafirma.php → polling.intervals)
 *  - `attempts` = número de intentos acumulados
 *  - `jitter_pct` = porcentaje de variación aleatoria (default ±20%)
 *
 * Beneficios:
 *  - Carga acotada: de 30s a ~40 min por solicitud individual
 *  - Jitter: evita ráfagas sincronizadas si N solicitudes se crean a la vez
 *  - Techo x8: el intervalo nunca crece más de 8x el base
 */
final class PollingScheduler
{
    /** @var array<string,int> */
    private readonly array $intervals;
    private readonly int $maxAttempts;
    private readonly int $expirationHours;
    private readonly int $jitterPct;

    public function __construct()
    {
        $this->intervals      = (array) config('viafirma.polling.intervals', []);
        $this->maxAttempts    = (int) config('viafirma.polling.max_attempts', 96);
        $this->expirationHours = (int) config('viafirma.polling.expiration_hours', 72);
        $this->jitterPct      = (int) config('viafirma.polling.jitter_pct', 20);
    }

    /**
     * Calcula el próximo delay (en segundos) basado en el estado remoto y el
     * número de intentos acumulados.
     */
    public function nextDelay(ViafirmaCertificateRequest $entity): int
    {
        $remoteStatus = $entity->remote_status ?? 'default';
        $attempts     = $entity->poll_attempts;

        $base   = $this->intervals[$remoteStatus] ?? $this->intervals['default'] ?? 180;
        $growth = min(2 ** (int) floor($attempts / 5), 8);
        $jitter = $this->calculateJitter($base);

        $delay = (int) max(10, ($base * $growth) + $jitter);

        return $delay;
    }

    /**
     * Delay corto tras error transient (red/5xx) — 30-60s con jitter.
     */
    public function retryAfter(ViafirmaCertificateRequest $entity): int
    {
        $base = 30;
        $jitter = $this->calculateJitter($base);
        return (int) max(15, $base + $jitter);
    }

    /**
     * ¿Se superó el máximo de intentos de polling?
     */
    public function hasExceededMaxAttempts(ViafirmaCertificateRequest $entity): bool
    {
        return $entity->poll_attempts >= $this->maxAttempts;
    }

    /**
     * ¿Se superó el SLA de tiempo (72h por defecto)?
     */
    public function hasExceededSla(ViafirmaCertificateRequest $entity): bool
    {
        if ($entity->submitted_at === null) {
            return false;
        }
        return $entity->submitted_at->addHours($this->expirationHours)->isPast();
    }

    /**
     * Jitter simétrico: ±jitter_pct% del base.
     */
    private function calculateJitter(int $base): int
    {
        if ($this->jitterPct <= 0) {
            return 0;
        }
        $maxJitter = (int) floor($base * $this->jitterPct / 100);
        if ($maxJitter <= 0) {
            return 0;
        }
        return random_int(-$maxJitter, $maxJitter);
    }
}
