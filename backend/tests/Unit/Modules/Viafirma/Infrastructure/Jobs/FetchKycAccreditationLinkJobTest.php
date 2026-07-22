<?php

namespace Tests\Unit\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Domain\Exceptions\TransientHttpException;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException;
use App\Modules\Viafirma\Infrastructure\Jobs\FetchKycAccreditationLinkJob;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FetchKycAccreditationLinkJobTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function persiste_link_en_exito(): void
    {
        // Arrange
        $state = ViafirmaCertificateRequestState::factory()->create([
            'internal_state' => InternalState::POLLING,
            'remote_status' => RemoteStatus::ACCREDITATION->value,
            'kyc_accreditation_link' => null,
        ]);

        $entity = ViafirmaCertificateRequest::factory()->create([
            'viafirma_certificate_request_state_id' => $state->id,
            'cod_request' => 'TEST-COD-001',
        ]);

        $expectedLink = 'https://kyc.viafirma.com/success?req=TEST-COD-001';

        $mockClient = $this->createMock(ViafirmaClient::class);
        $mockClient->expects($this->once())
            ->method('getAccreditationLink')
            ->with('TEST-COD-001')
            ->willReturn($expectedLink);

        // Act
        $job = new FetchKycAccreditationLinkJob($entity->id);
        $job->handle($mockClient, app(SafePemLogger::class));

        // Assert
        $state->refresh();
        $this->assertEquals($expectedLink, $state->kyc_accreditation_link);
    }

    #[Test]
    public function es_idempotente_si_link_ya_existe(): void
    {
        // Arrange
        $cachedLink = 'https://kyc.viafirma.com/cached';

        $state = ViafirmaCertificateRequestState::factory()->create([
            'internal_state' => InternalState::POLLING,
            'remote_status' => RemoteStatus::ACCREDITATION->value,
            'kyc_accreditation_link' => $cachedLink,
        ]);

        $entity = ViafirmaCertificateRequest::factory()->create([
            'viafirma_certificate_request_state_id' => $state->id,
            'cod_request' => 'TEST-COD-002',
        ]);

        $mockClient = $this->createMock(ViafirmaClient::class);
        $mockClient->expects($this->never())->method('getAccreditationLink');

        // Act
        $job = new FetchKycAccreditationLinkJob($entity->id);
        $job->handle($mockClient, app(SafePemLogger::class));

        // Assert
        $state->refresh();
        $this->assertEquals($cachedLink, $state->kyc_accreditation_link);
    }

    #[Test]
    public function no_relanza_error_cliente_no_transitorio(): void
    {
        // Arrange
        $state = ViafirmaCertificateRequestState::factory()->create([
            'internal_state' => InternalState::POLLING,
            'remote_status' => RemoteStatus::ACCREDITATION->value,
            'kyc_accreditation_link' => null,
        ]);

        $entity = ViafirmaCertificateRequest::factory()->create([
            'viafirma_certificate_request_state_id' => $state->id,
            'cod_request' => 'TEST-COD-003',
        ]);

        $mockClient = $this->createMock(ViafirmaClient::class);
        $mockClient->expects($this->once())
            ->method('getAccreditationLink')
            ->with('TEST-COD-003')
            ->willThrowException(new ViafirmaClientException('400 Bad Request: link_not_generated'));

        // Act â€” no debe relanzar excepciÃ³n
        $job = new FetchKycAccreditationLinkJob($entity->id);
        $job->handle($mockClient, app(SafePemLogger::class));

        // Assert â€” link no fue persistido pero el job completa sin error
        $state->refresh();
        $this->assertNull($state->kyc_accreditation_link);
    }

    #[Test]
    public function relanza_error_transitorio_para_reintento(): void
    {
        // Arrange
        $state = ViafirmaCertificateRequestState::factory()->create([
            'internal_state' => InternalState::POLLING,
            'remote_status' => RemoteStatus::ACCREDITATION->value,
            'kyc_accreditation_link' => null,
        ]);

        $entity = ViafirmaCertificateRequest::factory()->create([
            'viafirma_certificate_request_state_id' => $state->id,
            'cod_request' => 'TEST-COD-004',
        ]);

        $mockClient = $this->createMock(ViafirmaClient::class);
        $mockClient->expects($this->once())
            ->method('getAccreditationLink')
            ->with('TEST-COD-004')
            ->willThrowException(new TransientHttpException('502 Bad Gateway'));

        // Act & Assert
        $this->expectException(TransientHttpException::class);

        $job = new FetchKycAccreditationLinkJob($entity->id);
        $job->handle($mockClient, app(SafePemLogger::class));
    }

    #[Test]
    public function maneja_entidad_no_encontrada_gracefully(): void
    {
        // Arrange
        $mockClient = $this->createMock(ViafirmaClient::class);
        $mockClient->expects($this->never())->method('getAccreditationLink');

        // Act â€” no debe relanzar excepciÃ³n
        $job = new FetchKycAccreditationLinkJob(99999);
        $job->handle($mockClient, app(SafePemLogger::class));

        // El test pasa simplemente al completar sin error
        $this->assertTrue(true);
    }
}
