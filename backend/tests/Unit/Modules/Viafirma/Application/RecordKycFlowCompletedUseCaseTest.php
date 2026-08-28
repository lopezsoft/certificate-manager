<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Application;

use App\Modules\Viafirma\Application\UseCases\RecordKycFlowCompletedUseCase;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaCertificateRequestRepositoryContract;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 100% mockeado — no toca base de datos. El repositorio y el logger son
 * mocks del contrato/clase; la entidad y su `state` son instancias Eloquent
 * en memoria (nunca persistidas) con la relación inyectada vía setRelation().
 */
final class RecordKycFlowCompletedUseCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function registra_la_finalizacion_cuando_la_solicitud_existe(): void
    {
        $state = Mockery::mock(ViafirmaCertificateRequestState::class)->makePartial();
        $state->shouldReceive('save')->once()->andReturn(true);

        $entity = new ViafirmaCertificateRequest();
        $entity->id = 42;
        $entity->setRelation('state', $state);

        $repository = Mockery::mock(ViafirmaCertificateRequestRepositoryContract::class);
        $repository->shouldReceive('findByPublicId')
            ->once()
            ->with('PUB123')
            ->andReturn($entity);

        $logger = Mockery::mock(SafePemLogger::class);
        $logger->shouldReceive('info')->once()->with('viafirma.kyc_callback.recorded', [
            'id'        => 42,
            'public_id' => 'PUB123',
            'ip'        => '127.0.0.1',
        ]);

        $useCase = new RecordKycFlowCompletedUseCase($repository, $logger);
        $result  = $useCase->handle('PUB123', '127.0.0.1', 'Mozilla/5.0');

        $this->assertSame($entity, $result);
        $this->assertNotNull($state->kyc_flow_completed_at);
        $this->assertSame('127.0.0.1', $state->kyc_flow_completed_ip);
        $this->assertSame('Mozilla/5.0', $state->kyc_flow_completed_user_agent);
    }

    #[Test]
    public function retorna_null_y_registra_warning_cuando_no_existe_la_solicitud(): void
    {
        $repository = Mockery::mock(ViafirmaCertificateRequestRepositoryContract::class);
        $repository->shouldReceive('findByPublicId')
            ->once()
            ->with('DESCONOCIDO')
            ->andReturn(null);

        $logger = Mockery::mock(SafePemLogger::class);
        $logger->shouldReceive('warning')
            ->once()
            ->with('viafirma.kyc_callback.not_found', ['public_id' => 'DESCONOCIDO']);

        $useCase = new RecordKycFlowCompletedUseCase($repository, $logger);
        $result  = $useCase->handle('DESCONOCIDO', '10.0.0.1', 'AnyAgent');

        $this->assertNull($result);
    }

    #[Test]
    public function trunca_el_user_agent_a_500_caracteres(): void
    {
        $state = Mockery::mock(ViafirmaCertificateRequestState::class)->makePartial();
        $state->shouldReceive('save')->once()->andReturn(true);

        $entity = new ViafirmaCertificateRequest();
        $entity->setRelation('state', $state);

        $repository = Mockery::mock(ViafirmaCertificateRequestRepositoryContract::class);
        $repository->shouldReceive('findByPublicId')->once()->andReturn($entity);

        $logger = Mockery::mock(SafePemLogger::class);
        $logger->shouldReceive('info')->once();

        $useCase = new RecordKycFlowCompletedUseCase($repository, $logger);
        $useCase->handle('PUB', null, str_repeat('A', 600));

        $this->assertSame(500, strlen($state->kyc_flow_completed_user_agent));
    }
}
