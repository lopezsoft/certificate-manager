<?php

namespace App\Andes\Jobs;

use App\Andes\Contracts\AndesPkiServiceContract;
use App\Andes\Events\AndesCertificateEmitted;
use App\Andes\Exceptions\AndesCertificateEmissionException;
use App\Andes\Models\AndesCertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * PollAndesCertificateStatusJob
 *
 * Consulta periódicamente el estado de una solicitud en ANDES PKI.
 * Se re-encola a sí mismo hasta que el certificado sea emitido o se alcancen los intentos máximos.
 *
 * Intervalo: configurable (default 1h)
 * Máximo: configurable (default 48 iteraciones = 48h)
 */
class PollAndesCertificateStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;   // No reintentar automáticamente — lo controlamos nosotros
    public int $timeout = 60;

    public function __construct(
        private readonly int $andesCertificateRequestId,
        private readonly int $attempt = 1,
    ) {}

    public function handle(AndesPkiServiceContract $pkiService): void
    {
        $maxAttempts = (int) config('andes.polling_max_attempts', 48);
        $interval    = (int) config('andes.polling_interval', 3600);

        $andesReq = AndesCertificateRequest::with('certificateRequest')
            ->find($this->andesCertificateRequestId);

        if (! $andesReq) {
            Log::warning('[ANDES-PKI] PollJob: AndesCertificateRequest no encontrado.', [
                'id' => $this->andesCertificateRequestId,
            ]);
            return;
        }

        // Si ya está emitido o revocado, no seguir
        if ($andesReq->isEmitted() || $andesReq->isRevoked()) {
            Log::info('[ANDES-PKI] PollJob: Certificado ya resuelto, cancelando polling.', [
                'id' => $this->andesCertificateRequestId,
            ]);
            return;
        }

        Log::info('[ANDES-PKI] PollJob: Consultando estado.', [
            'id'         => $this->andesCertificateRequestId,
            'attempt'    => $this->attempt,
            'solicitudId'=> $andesReq->andes_solicitud_id,
        ]);

        try {
            $queryResponse = $pkiService->queryRequestStatus(
                solicitudId: $andesReq->andes_solicitud_id,
                documento:   $andesReq->certificateRequest->document_number,
            );

            // Actualizar estado en BD
            $andesReq->update([
                'andes_estado'    => $queryResponse->estado,
                'andes_message'   => $queryResponse->message,
                'andes_raw_response' => $queryResponse->rawResponse,
            ]);

            if ($queryResponse->isEmitted()) {
                // ✅ Certificado emitido
                $andesReq->update([
                    'certificate_serial' => $queryResponse->serial,
                    'emitted_at'         => now(),
                ]);

                // Actualizar estado del certificate_request padre
                $andesReq->certificateRequest->update([
                    'request_status' => 'PROCESSED',
                ]);

                Log::info('[ANDES-PKI] Certificado emitido correctamente.', [
                    'serial' => $queryResponse->serial,
                    'id'     => $this->andesCertificateRequestId,
                ]);

                AndesCertificateEmitted::dispatch($andesReq);
                return;
            }

            // ⏳ Aún en proceso — re-encolar si no se alcanzó el máximo
            if ($this->attempt < $maxAttempts) {
                self::dispatch($this->andesCertificateRequestId, $this->attempt + 1)
                    ->delay(now()->addSeconds($interval));

                Log::info('[ANDES-PKI] PollJob re-encolado.', [
                    'next_attempt' => $this->attempt + 1,
                    'in_seconds'   => $interval,
                ]);
            } else {
                // ❌ Máximo de intentos alcanzado
                $andesReq->certificateRequest->update([
                    'request_status' => 'PROCESSING', // Admin decide qué hacer
                ]);

                Log::error('[ANDES-PKI] PollJob: Máximo de intentos alcanzado sin emisión.', [
                    'id'       => $this->andesCertificateRequestId,
                    'attempts' => $this->attempt,
                ]);
            }
        } catch (AndesCertificateEmissionException $e) {
            Log::error('[ANDES-PKI] PollJob: Error al consultar ANDES.', [
                'error' => $e->getMessage(),
                'id'    => $this->andesCertificateRequestId,
            ]);

            // Re-encolar igualmente (puede ser error transitorio)
            if ($this->attempt < $maxAttempts) {
                self::dispatch($this->andesCertificateRequestId, $this->attempt + 1)
                    ->delay(now()->addSeconds($interval));
            }
        }
    }
}

