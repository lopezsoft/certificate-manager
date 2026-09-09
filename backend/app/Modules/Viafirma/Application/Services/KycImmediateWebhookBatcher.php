<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Services;

use App\Modules\Viafirma\Infrastructure\Jobs\FlushKycImmediateWebhookJob;
use Illuminate\Support\Facades\Cache;

/**
 * Agrupa avisos de WhatsApp del envío inmediato (KYC) por empresa, en una
 * ventana de espera corta, para no disparar un mensaje por cada solicitud
 * individual cuando varias de la misma empresa capturan su link casi al
 * mismo tiempo.
 *
 * Patrón: buffer en caché + job de flush con delay, deduplicado por empresa.
 * Si ya hay un flush programado para la empresa, los nuevos `enqueue()` solo
 * agregan al buffer — no programan un segundo flush; el pendiente recogerá
 * todo lo acumulado al ejecutarse.
 *
 * ⚠️ Requiere CACHE_DRIVER persistente entre workers (file/redis/database).
 * Con 'array' el buffer no sobrevive entre el enqueue() y el flush() si
 * corren en procesos distintos — mismo requisito ya documentado para
 * MockViafirmaClient.
 */
class KycImmediateWebhookBatcher
{
    private const BUFFER_TTL_MINUTES = 5;

    public function enqueue(
        int $companyId,
        string $casaSoftware,
        ?string $whatsapp,
        string $codigo,
        string $enlace,
    ): void {
        $bufferKey = $this->bufferKey($companyId);

        $buffer = Cache::get($bufferKey, []);
        $buffer[] = ['codigo' => $codigo, 'enlace' => $enlace];
        Cache::put($bufferKey, $buffer, now()->addMinutes(self::BUFFER_TTL_MINUTES));

        $lockKey = $this->lockKey($companyId);
        $windowSeconds = (int) config('viafirma.kyc.webhook_batch_window_seconds', 20);

        // add() solo tiene éxito si la clave NO existe — evita programar un
        // segundo flush si ya hay uno pendiente para esta empresa.
        $scheduled = Cache::add($lockKey, true, now()->addSeconds($windowSeconds + 30));

        if ($scheduled) {
            FlushKycImmediateWebhookJob::dispatch($companyId, $casaSoftware, $whatsapp)
                ->delay(now()->addSeconds($windowSeconds));
        }
    }

    /**
     * @return array<int, array{codigo: string, enlace: string}>
     */
    public function pullBuffer(int $companyId): array
    {
        $bufferKey = $this->bufferKey($companyId);
        $buffer = Cache::get($bufferKey, []);

        Cache::forget($bufferKey);
        Cache::forget($this->lockKey($companyId));

        return $buffer;
    }

    private function bufferKey(int $companyId): string
    {
        return "kyc_webhook_batch_{$companyId}";
    }

    private function lockKey(int $companyId): string
    {
        return "kyc_webhook_batch_scheduled_{$companyId}";
    }
}
