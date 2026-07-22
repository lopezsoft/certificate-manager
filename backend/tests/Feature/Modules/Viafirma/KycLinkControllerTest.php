<?php

namespace Tests\Feature\Modules\Viafirma;

use App\Models\CertificateRequest;
use App\Models\Company;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class KycLinkControllerTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function obtiene_link_cacheado_exitosamente(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $certificateRequest = CertificateRequest::factory()->create([
            'company_id' => $company->id,
        ]);

        $state = ViafirmaCertificateRequestState::factory()->create([
            'internal_state' => InternalState::POLLING,
            'remote_status' => RemoteStatus::ACCREDITATION->value,
            'kyc_accreditation_link' => 'https://kyc.viafirma.com/cached',
        ]);

        ViafirmaCertificateRequest::factory()->create([
            'certificate_request_id' => $certificateRequest->id,
            'viafirma_certificate_request_state_id' => $state->id,
            'cod_request' => 'TEST-COD-001',
        ]);

        // Act
        $response = $this->getJson("/api/v1/certificate-request/{$certificateRequest->id}/kyc-link");

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.link', 'https://kyc.viafirma.com/cached');
    }

    #[Test]
    public function retorna_422_con_mensaje_de_estado_real_cuando_no_es_accreditation(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $certificateRequest = CertificateRequest::factory()->create([
            'company_id' => $company->id,
        ]);

        $state = ViafirmaCertificateRequestState::factory()->create([
            'internal_state' => InternalState::SUBMITTED,
            'remote_status' => RemoteStatus::RUES_CHECK->value,
        ]);

        ViafirmaCertificateRequest::factory()->create([
            'certificate_request_id' => $certificateRequest->id,
            'viafirma_certificate_request_state_id' => $state->id,
            'cod_request' => 'TEST-COD-002',
        ]);

        // Act
        $response = $this->getJson("/api/v1/certificate-request/{$certificateRequest->id}/kyc-link");

        // Assert
        $response->assertStatus(422);
        $response->assertJsonPath('message', function ($message) {
            // Debe mostrar el estado remoto real, no "null"
            return str_contains($message, 'rues_check') || str_contains($message, 'Estado remoto actual: rues_check');
        });
    }

    #[Test]
    public function retorna_404_cuando_no_existe_viafirma_certificate_request(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $certificateRequest = CertificateRequest::factory()->create([
            'company_id' => $company->id,
        ]);

        // No crear ViafirmaCertificateRequest asociado

        // Act
        $response = $this->getJson("/api/v1/certificate-request/{$certificateRequest->id}/kyc-link");

        // Assert
        $response->assertStatus(404);
    }

    #[Test]
    public function obtiene_link_en_vivo_cuando_no_esta_cacheado_y_estado_es_accreditation(): void
    {
        // Arrange
        // Usar MockViafirmaClient que estÃ¡ registrado en tests
        $company = Company::factory()->create();
        $certificateRequest = CertificateRequest::factory()->create([
            'company_id' => $company->id,
        ]);

        $state = ViafirmaCertificateRequestState::factory()->create([
            'internal_state' => InternalState::POLLING,
            'remote_status' => RemoteStatus::ACCREDITATION->value,
            'kyc_accreditation_link' => null,
        ]);

        ViafirmaCertificateRequest::factory()->create([
            'certificate_request_id' => $certificateRequest->id,
            'viafirma_certificate_request_state_id' => $state->id,
            'cod_request' => 'TEST-LIVE-COD',
        ]);

        // Act
        $response = $this->getJson("/api/v1/certificate-request/{$certificateRequest->id}/kyc-link");

        // Assert â€” MockViafirmaClient retorna https://sandbox.viafirma.com/accreditation/success?req={codRequest}
        $response->assertStatus(200);
        $response->assertJsonPath('data.link', function ($link) {
            return str_contains($link, 'sandbox.viafirma.com/accreditation') && str_contains($link, 'TEST-LIVE-COD');
        });

        // Verificar que el link se persistiÃ³
        $state->refresh();
        $this->assertNotNull($state->kyc_accreditation_link);
    }
}
