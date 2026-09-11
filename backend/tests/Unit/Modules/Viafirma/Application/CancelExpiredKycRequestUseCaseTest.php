<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Application;

use App\Models\CertificateRequest;
use App\Models\Company;
use App\Modules\Viafirma\Application\UseCases\CancelExpiredKycRequestUseCase;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\StateMachine;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use App\Services\QuotaService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 100% mockeado — no toca base de datos. StateMachine y QuotaService son
 * mocks; la entidad, su state y certificateRequest son instancias Eloquent
 * en memoria (nunca persistidas, `save()` interceptado con Mockery donde
 * aplica).
 */
final class CancelExpiredKycRequestUseCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeEntity(
        InternalState $internalState = InternalState::POLLING,
        ?\Illuminate\Support\Carbon $kycFlowCompletedAt = null,
        bool $withCompany = true,
    ): ViafirmaCertificateRequest {
        $state = new ViafirmaCertificateRequestState();
        $state->internal_state         = $internalState;
        $state->kyc_flow_completed_at  = $kycFlowCompletedAt;

        $certificateRequest = Mockery::mock(CertificateRequest::class)->makePartial();
        $certificateRequest->legal_rep_first_name = 'Juan';
        $certificateRequest->legal_rep_last_name  = 'Perez';
        $certificateRequest->company_name         = null;

        if ($withCompany) {
            $company = new Company();
            $company->id = 42;
            $certificateRequest->setRelation('company', $company);
        }

        $entity = new ViafirmaCertificateRequest();
        $entity->id          = 99;
        $entity->cod_request = 'ABC123';
        $entity->setRelation('state', $state);
        $entity->setRelation('certificateRequest', $certificateRequest);

        return $entity;
    }

    #[Test]
    public function cancela_y_reintegra_el_cupo_en_el_caso_feliz(): void
    {
        $entity = $this->makeEntity();
        $certificateRequest = $entity->certificateRequest;
        // Ya no se guarda aquí — StateMachine::markExpired() dispara
        // ViafirmaStatusChanged, que ViafirmaRequestStateChangedListener
        // escucha para sincronizar request_status + change_histories.
        // Como StateMachine está mockeado, ese evento no se dispara en este
        // test — se verifica solo que el use case NO intente guardar por su
        // cuenta (evitaría una doble escritura).
        $certificateRequest->shouldNotReceive('save');

        $stateMachine = Mockery::mock(StateMachine::class);
        $stateMachine->shouldReceive('markExpired')->once()->with($entity);

        $quotaService = Mockery::mock(QuotaService::class);
        $quotaService->shouldReceive('releaseQuotaForRequest')->once()->with(42)->andReturn(true);

        $logger = Mockery::mock(SafePemLogger::class);
        $logger->shouldReceive('info')->once()->with('viafirma.kyc_expire.cancelled', [
            'id'          => 99,
            'cod_request' => 'ABC123',
            'company_id'  => 42,
        ]);

        $useCase = new CancelExpiredKycRequestUseCase($stateMachine, $quotaService, $logger);
        $result  = $useCase->handle($entity);

        $this->assertSame('Juan Perez', $result);
    }

    #[Test]
    public function no_cancela_si_el_state_es_null(): void
    {
        $entity = new ViafirmaCertificateRequest();
        $entity->id = 1;
        $entity->setRelation('state', null);

        $stateMachine = Mockery::mock(StateMachine::class);
        $stateMachine->shouldNotReceive('markExpired');

        $quotaService = Mockery::mock(QuotaService::class);
        $quotaService->shouldNotReceive('releaseQuotaForRequest');

        $logger = Mockery::mock(SafePemLogger::class);

        $useCase = new CancelExpiredKycRequestUseCase($stateMachine, $quotaService, $logger);

        $this->assertNull($useCase->handle($entity));
    }

    #[Test]
    public function no_cancela_si_ya_esta_en_estado_terminal(): void
    {
        $entity = $this->makeEntity(internalState: InternalState::COMPLETED);

        $stateMachine = Mockery::mock(StateMachine::class);
        $stateMachine->shouldNotReceive('markExpired');

        $quotaService = Mockery::mock(QuotaService::class);
        $quotaService->shouldNotReceive('releaseQuotaForRequest');

        $logger = Mockery::mock(SafePemLogger::class);

        $useCase = new CancelExpiredKycRequestUseCase($stateMachine, $quotaService, $logger);

        $this->assertNull($useCase->handle($entity));
    }

    #[Test]
    public function no_cancela_si_el_usuario_ya_completo_el_kyc_en_el_margen(): void
    {
        // Protección de carrera: kyc_flow_completed_at ya no es null aunque
        // internal_state siga en POLLING (el callback aún no propagó el
        // cambio de estado real vía polling).
        $entity = $this->makeEntity(kycFlowCompletedAt: now());

        $stateMachine = Mockery::mock(StateMachine::class);
        $stateMachine->shouldNotReceive('markExpired');

        $quotaService = Mockery::mock(QuotaService::class);
        $quotaService->shouldNotReceive('releaseQuotaForRequest');

        $logger = Mockery::mock(SafePemLogger::class);

        $useCase = new CancelExpiredKycRequestUseCase($stateMachine, $quotaService, $logger);

        $this->assertNull($useCase->handle($entity));
    }

    #[Test]
    public function no_cancela_si_no_hay_empresa_asociada(): void
    {
        $entity = $this->makeEntity(withCompany: false);

        $stateMachine = Mockery::mock(StateMachine::class);
        $stateMachine->shouldNotReceive('markExpired');

        $quotaService = Mockery::mock(QuotaService::class);
        $quotaService->shouldNotReceive('releaseQuotaForRequest');

        $logger = Mockery::mock(SafePemLogger::class);
        $logger->shouldReceive('warning')->once()->with('viafirma.kyc_expire.no_company', ['id' => 99]);

        $useCase = new CancelExpiredKycRequestUseCase($stateMachine, $quotaService, $logger);

        $this->assertNull($useCase->handle($entity));
    }

    #[Test]
    public function el_nombre_del_solicitante_incluye_la_empresa_para_fe_pj(): void
    {
        $entity = $this->makeEntity();
        $entity->certificateRequest->company_name = 'ACME SAS';
        $entity->certificateRequest->shouldNotReceive('save');

        $stateMachine = Mockery::mock(StateMachine::class);
        $stateMachine->shouldReceive('markExpired')->once();

        $quotaService = Mockery::mock(QuotaService::class);
        $quotaService->shouldReceive('releaseQuotaForRequest')->once()->andReturn(true);

        $logger = Mockery::mock(SafePemLogger::class);
        $logger->shouldReceive('info')->once();

        $useCase = new CancelExpiredKycRequestUseCase($stateMachine, $quotaService, $logger);

        $this->assertSame('Juan Perez (ACME SAS)', $useCase->handle($entity));
    }
}
