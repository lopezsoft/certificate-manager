<?php

namespace Tests\Unit\Modules\Viafirma\Application;

use App\Modules\Viafirma\Application\UseCases\GetKycLinkUseCase;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GetKycLinkUseCaseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function retorna_link_cacheado_sin_llamar_cliente(): void
    {
        // Arrange
        $state = ViafirmaCertificateRequestState::factory()->create([
            'internal_state' => InternalState::POLLING,
            'remote_status' => RemoteStatus::ACCREDITATION->value,
            'kyc_accreditation_link' => 'https://kyc.viafirma.com/accreditation/cached',
        ]);

        $entity = ViafirmaCertificateRequest::factory()->create([
            'viafirma_certificate_request_state_id' => $state->id,
            'cod_request' => 'TEST-COD-001',
        ]);

        $mockClient = $this->createMock(ViafirmaClient::class);
        $mockClient->expects($this->never())->method('getAccreditationLink');

        $useCase = new GetKycLinkUseCase(
            client: $mockClient,
            logger: app(SafePemLogger::class),
        );

        // Act
        $link = $useCase->handle($entity->id);

        // Assert
        $this->assertEquals('https://kyc.viafirma.com/accreditation/cached', $link);
    }

    #[Test]
    public function lanza_excepcion_con_estado_real_cuando_no_es_accreditation(): void
    {
        // Arrange
        $state = ViafirmaCertificateRequestState::factory()->create([
            'internal_state' => InternalState::SUBMITTED,
            'remote_status' => RemoteStatus::RUES_CHECK->value,
        ]);

        $entity = ViafirmaCertificateRequest::factory()->create([
            'viafirma_certificate_request_state_id' => $state->id,
            'cod_request' => 'TEST-COD-002',
        ]);

        $useCase = new GetKycLinkUseCase(
            client: $this->createMock(ViafirmaClient::class),
            logger: app(SafePemLogger::class),
        );

        // Act & Assert
        $this->expectException(ViafirmaException::class);
        $this->expectExceptionMessageMatches('/rues_check/');

        $useCase->handle($entity->id);
    }

    #[Test]
    public function lanza_excepcion_cuando_no_hay_cod_request(): void
    {
        // Arrange
        $state = ViafirmaCertificateRequestState::factory()->create([
            'internal_state' => InternalState::POLLING,
            'remote_status' => RemoteStatus::ACCREDITATION->value,
        ]);

        $entity = ViafirmaCertificateRequest::factory()->create([
            'viafirma_certificate_request_state_id' => $state->id,
            'cod_request' => null,
        ]);

        $useCase = new GetKycLinkUseCase(
            client: $this->createMock(ViafirmaClient::class),
            logger: app(SafePemLogger::class),
        );

        // Act & Assert
        $this->expectException(ViafirmaException::class);
        $this->expectExceptionMessageMatches('/cod_request/');

        $useCase->handle($entity->id);
    }

    #[Test]
    public function obtiene_y_persiste_link_en_vivo_cuando_no_esta_cacheado(): void
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

        $expectedLink = 'https://kyc.viafirma.com/accreditation/live';

        $mockClient = $this->createMock(ViafirmaClient::class);
        $mockClient->expects($this->once())
            ->method('getAccreditationLink')
            ->with('TEST-COD-003')
            ->willReturn($expectedLink);

        $useCase = new GetKycLinkUseCase(
            client: $mockClient,
            logger: app(SafePemLogger::class),
        );

        // Act
        $link = $useCase->handle($entity->id);

        // Assert
        $this->assertEquals($expectedLink, $link);

        // Verificar que se persistió
        $state->refresh();
        $this->assertEquals($expectedLink, $state->kyc_accreditation_link);
    }

    #[Test]
    public function lanza_excepcion_cuando_entidad_no_existe(): void
    {
        $useCase = new GetKycLinkUseCase(
            client: $this->createMock(ViafirmaClient::class),
            logger: app(SafePemLogger::class),
        );

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $useCase->handle(99999);
    }
}
