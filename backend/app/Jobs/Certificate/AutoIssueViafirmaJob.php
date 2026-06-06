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

    /**
     * El número de intentos permitidos.
     */
    public int $tries = 3;

    /**
     * Esperar 30 segundos antes de reintentar.
     */
    public int $backoff = 30;

    public function __construct(
        public readonly int $certificateRequestId,
        public readonly ?int $userId = null
    ) {}

    public function handle(CertificateIssuanceOrchestrator $orchestrator): void
    {
        $cr = CertificateRequest::query()->find($this->certificateRequestId);

        if ($cr === null) {
            Log::warning('AutoIssueViafirmaJob: Solicitud no encontrada', ['id' => $this->certificateRequestId]);
            return;
        }

        try {
            $organizationType = null;
            
            // Si es Persona Jurídica (1), determinamos el organization_type de Viafirma
            if ($cr->type_organization_id === 1) {
                // entity_document_type_id = 1 corresponde a Cámara de Comercio (Registro Mercantil)
                $organizationType = $cr->entity_document_type_id === 1
                    ? OrganizationType::RM->value
                    : OrganizationType::ESAL->value; // Fallback razonable para Personería Jurídica, Actas, etc.
            }

            $requestDto = new IssuanceRequest(
                certificateRequestId: $cr->id,
                requestedByUserId:    $this->userId,
                emailCertificate:     $cr->legal_rep_email,
                organizationType:     $organizationType,
                providerHint:         'viafirma'
            );

            Log::info('AutoIssueViafirmaJob: Iniciando emisión asíncrona', ['cr_id' => $cr->id]);

            $orchestrator->dispatch($requestDto);

        } catch (Throwable $e) {
            Log::error('AutoIssueViafirmaJob: Fallo en la emisión', [
                'cr_id' => $cr->id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
}
