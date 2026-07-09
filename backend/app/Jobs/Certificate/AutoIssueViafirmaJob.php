<?php

declare(strict_types=1);

namespace App\Jobs\Certificate;

use App\DTOs\Certificate\IssuanceRequest;
use App\Exceptions\Certificate\CertificateDataIntegrityException;
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
                throw new CertificateDataIntegrityException(
                    "CertificateRequest {$this->certificateRequestId} no encontrada. Esto indica un bug de validación upstream o un caso de datos faltantes."
                );
                return; // No reintentar: la solicitud no existe
            }

            // legal_rep_email obligatorio — sin él Viafirma no puede emitir.
            // CreateCertificateRequestFormRequest ya lo exige para provider=viafirma;
            // si llega vacío aquí es un bug de validación upstream, no un caso legítimo.
            $emailCertificate = $cr->legal_rep_email ?? null;
            if (empty($emailCertificate)) {
                throw new CertificateDataIntegrityException(
                    "CertificateRequest {$cr->id}: legal_rep_email vacío. Esto debió ser rechazado en CreateCertificateRequestFormRequest al crear la solicitud."
                );
            }

            $organizationType = $this->resolveOrganizationType($cr);

            $requestDto = new IssuanceRequest(
                certificateRequestId: $cr->id,
                requestedByUserId:    $this->userId,
                emailCertificate:     $emailCertificate,
                organizationType:     $organizationType,
                providerHint:         'viafirma',
            );

            Log::info('AutoIssueViafirmaJob: llamando al orquestador', [
                'cr_id'             => $cr->id,
                'organization_type' => $organizationType,
                'attempt'           => $this->attempts(),
                'requestDto'        => $requestDto,
            ]);

            // dispatchAsSystem respeta el providerHint sin requerir callerIsAdmin
            $result = $orchestrator->dispatchAsSystem($requestDto);

            Log::info('AutoIssueViafirmaJob: emisión completada', [
                'cr_id'      => $cr->id,
                'status'     => $result->status,
                'elapsed_ms' => round((microtime(true) - $startedAt) * 1000),
            ]);

        } catch (CertificateDataIntegrityException $e) {
            // Dato estructural inválido: indica un bug de validación upstream
            // (debió rechazarse en CreateCertificateRequestFormRequest). No es
            // un error transitorio — reintentar no lo resuelve. Falla de inmediato
            // y queda visible en failed_jobs, sin consumir los 3 tries.
            Log::error('AutoIssueViafirmaJob: dato estructural inválido — fallo inmediato sin reintento', [
                'cr_id'   => $this->certificateRequestId,
                'attempt' => $this->attempts(),
                'error'   => $e->getMessage(),
            ]);
            $this->fail($e);
            return;
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

    /**
     * Resuelve el tipo de organización para Viafirma basado en entity_document_type.
     *
     * - Persona Natural → null (sin tipo)
     * - Persona Jurídica con código mapeado al enum OrganizationType → el valor correspondiente
     *
     * IMPORTANTE: CreateCertificateRequestFormRequest ya garantiza que,
     * para Persona Jurídica, el entity_document_type_id está presente, es activo,
     * y su código mapea al enum. Si llega un caso que no cumple esto, es un bug
     * de validación upstream y se lanza CertificateDataIntegrityException
     * (no se reintenta automáticamente, indicando error de validación, no
     * error transitorio).
     *
     * @return string|null OrganizationType value para PJ, null para PN
     * @throws CertificateDataIntegrityException si hay datos estructurales inválidos
     */
    private function resolveOrganizationType(CertificateRequest $cr): ?string
    {
        // PN → null (sin cambios)
        if ((int) $cr->type_organization_id !== 1) {
            return null;
        }

        // PJ: cargar tipo de documento constitutivo
        $entityDocType = $cr->entityDocumentType ?: $cr->load('entityDocumentType')->entityDocumentType;

        if ($entityDocType === null) {
            throw new CertificateDataIntegrityException(
                "CertificateRequest {$cr->id}: Persona Jurídica sin entity_document_type asociado. Esto debió ser rechazado en CreateCertificateRequestFormRequest al crear la solicitud."
            );
        }

        // Mapear código a enum OrganizationType
        $organizationType = OrganizationType::tryFrom($entityDocType->code);

        if ($organizationType === null) {
            throw new CertificateDataIntegrityException(
                "CertificateRequest {$cr->id}: entity_document_type_id={$entityDocType->id} (code='{$entityDocType->code}') no mapea a ningún OrganizationType soportado por Viafirma. Esto debió ser rechazado en CreateCertificateRequestFormRequest al crear la solicitud."
            );
        }

        return $organizationType->value;
    }
}
