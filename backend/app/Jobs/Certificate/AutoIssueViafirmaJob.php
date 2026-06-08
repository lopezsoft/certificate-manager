<?php

declare(strict_types=1);

namespace App\Jobs\Certificate;

use App\DTOs\Certificate\IssuanceRequest;
use App\Models\CertificateRequest;
use App\Modules\Viafirma\Domain\Enums\OrganizationType;
use App\Services\Certificate\CertificateIssuanceOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoIssueViafirmaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Intentos totales (1 inmediato + 2 reintentos). */
    public int $tries = 3;

    /** Segundos de espera entre reintentos. */
    public int $backoff = 30;

    /** Timeout del job (segundos). Suficiente para generación de llave + HTTP. */
    public int $timeout = 90;

    public function __construct(
        public readonly int $certificateRequestId,
        public readonly ?int $userId = null
    ) {}

    public function handle(CertificateIssuanceOrchestrator $orchestrator): void
    {
        $startedAt = microtime(true);

        Log::info('AutoIssueViafirmaJob: iniciando', [
            'cr_id'   => $this->certificateRequestId,
            'attempt' => $this->attempts(),
        ]);

        try {
            $cr = CertificateRequest::query()->find($this->certificateRequestId);

            if ($cr === null) {
                Log::warning('AutoIssueViafirmaJob: solicitud no encontrada — se abandona', [
                    'cr_id' => $this->certificateRequestId,
                ]);
                return; // No reintentar: la solicitud no existe
            }

            // legal_rep_email obligatorio — sin él Viafirma no puede emitir
            $emailCertificate = $cr->legal_rep_email ?? null;
            if (empty($emailCertificate)) {
                Log::warning('AutoIssueViafirmaJob: sin legal_rep_email — se abandona', [
                    'cr_id' => $cr->id,
                ]);
                return; // No reintentar: falta dato estructural
            }

            // PJ (type_organization_id == 1) → EXTRANJERAS
            // PN (type_organization_id == 2) → null (no se envía el campo)
            // Usar == (no ===) porque Eloquent puede retornar string "1" sin cast
            $organizationType = ((int) $cr->type_organization_id === 1)
                ? OrganizationType::EXTRANJERAS->value
                : null;

            $requestDto = new IssuanceRequest(
                certificateRequestId: $cr->id,
                requestedByUserId:    $this->userId,
                emailCertificate:     $emailCertificate,
                organizationType:     $organizationType,
                providerHint:         'viafirma',
            );

            Log::info('AutoIssueViafirmaJob: llamando al orquestador', [
                'cr_id'            => $cr->id,
                'organization_type' => $organizationType ?? 'null (PN)',
                'attempt'          => $this->attempts(),
            ]);

            // dispatchAsSystem respeta el providerHint sin requerir callerIsAdmin
            $result = $orchestrator->dispatchAsSystem($requestDto);

            Log::info('AutoIssueViafirmaJob: emisión completada', [
                'cr_id'      => $cr->id,
                'status'     => $result->status,
                'elapsed_ms' => round((microtime(true) - $startedAt) * 1000),
            ]);

        } catch (Throwable $e) {
            $elapsed = round((microtime(true) - $startedAt) * 1000);

            Log::error('AutoIssueViafirmaJob: fallo en emisión', [
                'cr_id'      => $this->certificateRequestId,
                'attempt'    => $this->attempts(),
                'error'      => $e->getMessage(),
                'class'      => get_class($e),
                'elapsed_ms' => $elapsed,
            ]);

            // Re-lanzar para que Laravel marque el intento como FAILED
            // y lo reintente hasta $tries veces.
            throw $e;
        }
    }

    /**
     * Se ejecuta cuando se agotan todos los intentos.
     * Registra el fallo definitivo sin relanzar (evita que el worker muera).
     */
    public function failed(Throwable $exception): void
    {
        Log::error('AutoIssueViafirmaJob: todos los intentos fallaron', [
            'cr_id' => $this->certificateRequestId,
            'error' => $exception->getMessage(),
            'class' => get_class($exception),
        ]);
    }
}
