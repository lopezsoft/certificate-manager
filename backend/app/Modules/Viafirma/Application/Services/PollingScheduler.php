<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Services;

use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;

/**
 * Calcula el intervalo entre polls de estado Viafirma.
 *
 * Estrategia: intervalo FIJO configurado en VIAFIRMA_POLL_INTERVAL (default 60s).
 * No hay backoff exponencial — el proceso completo de acreditación Viafirma
 * puede tardar entre minutos y horas; se consulta cada minuto hasta resolverse.
 *
 * Flujo de 3 endpoints:
 *   1. POST /request/fromCSR        → obtiene codRequest + publicId
 *   2. GET  /request/{cod}/status   → poll cada 60s hasta Generated_Not_Downloaded
 *   3. GET  /downloadCertificateServlet?req={publicId} → descarga P7B
 */
final class PollingScheduler
{
    private readonly int $intervalSeconds;
    private readonly int $maxAttempts;
    private readonly int $expirationHours;

    public function __construct()
    {
        $this->intervalSeconds  = max(10, (int) config('viafirma.polling.interval_seconds', 60));
        $this->maxAttempts      = (int) config('viafirma.polling.max_attempts', 288);
        $this->expirationHours  = (int) config('viafirma.polling.expiration_hours', 72);
    }

    /**
     * Devuelve el delay (segundos) hasta el próximo poll.
     * Intervalo fijo para todos los estados remotos.
     */
    public function nextDelay(ViafirmaCertificateRequest $entity): int
    {
        return $this->intervalSeconds;
    }

    /**
     * Delay corto tras error transient (red/5xx) — mismo intervalo base.
     */
    public function retryAfter(ViafirmaCertificateRequest $entity): int
    {
        return $this->intervalSeconds;
    }

    /**
     * ¿Se superó el máximo de intentos de polling?
     */
    public function hasExceededMaxAttempts(ViafirmaCertificateRequest $entity): bool
    {
        return $entity->poll_attempts >= $this->maxAttempts;
    }

    /**
     * ¿Se superó el SLA de tiempo configurado (default 72h)?
     */
    public function hasExceededSla(ViafirmaCertificateRequest $entity): bool
    {
        if ($entity->submitted_at === null) {
            return false;
        }
        return $entity->submitted_at->addHours($this->expirationHours)->isPast();
    }
}
