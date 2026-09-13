<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Listeners;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use App\Modules\Viafirma\Application\Listeners\ViafirmaRequestStateChangedListener;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Domain\Events\ViafirmaStatusChanged;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Mockery;
use Psr\Log\NullLogger;
use Tests\TestCase;
use Tests\Unit\Modules\Viafirma\CreatesViafirmaSchemaInMemory;

/**
 * Tests para ViafirmaRequestStateChangedListener.
 *
 * Sin BD real: la solicitud es un mock parcial de CertificateRequest, de modo
 * que `update()` se verifica como expectativa en lugar de escribir. La única
 * tabla creada es `change_histories` en SQLite :memory:, porque el listener
 * hace `ChangeHistory::create()` directo y no es interceptable.
 */
class ViafirmaRequestStateChangedListenerTest extends TestCase
{
    use CreatesViafirmaSchemaInMemory;

    private ViafirmaRequestStateChangedListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createChangeHistoriesTable();

        $this->listener = new ViafirmaRequestStateChangedListener(
            new SafePemLogger(new NullLogger()),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Verifica las expectativas de Mockery AQUÍ (no en tearDown) para que
     * cuenten como aserciones y PHPUnit no marque el test como "risky".
     */
    private function assertMockExpectationsMet(): void
    {
        Mockery::getContainer()->mockery_verify();
        $this->addToAssertionCount(1);
    }

    /**
     * Mock parcial: se comporta como el modelo pero `update()` es una
     * expectativa, nunca una escritura.
     *
     * @return CertificateRequest&Mockery\MockInterface
     */
    private function makeCertificateRequest(string $status)
    {
        $mock = Mockery::mock(CertificateRequest::class)->makePartial();
        $mock->id             = 42;
        $mock->request_status = $status;

        return $mock;
    }

    private function makeEntity(
        CertificateRequest $certificateRequest,
        InternalState      $newState,
        string             $remoteStatus = 'fail',
    ): ViafirmaCertificateRequest {
        $entity = new ViafirmaCertificateRequest();
        $entity->id = 1;
        $entity->certificate_request_id = 42;

        $state = new ViafirmaCertificateRequestState();
        $state->internal_state     = $newState;
        $state->remote_status      = $remoteStatus;
        $state->last_error_message = null;
        $entity->setRelation('state', $state);
        $entity->setRelation('certificateRequest', $certificateRequest);

        return $entity;
    }

    private function fire(
        ViafirmaCertificateRequest $entity,
        InternalState              $previous,
        InternalState              $new,
    ): void {
        $this->listener->handle(new ViafirmaStatusChanged(
            entity:        $entity,
            previousState: $previous,
            newState:      $new,
            remoteStatus:  RemoteStatus::FAIL,
        ));
    }

    public function test_auto_reject_when_internal_state_is_failed(): void
    {
        $certificateRequest = $this->makeCertificateRequest(
            CertificateRequestStatusEnum::PROCESSING->value,
        );

        $certificateRequest->shouldReceive('update')
            ->once()
            ->with(['request_status' => CertificateRequestStatusEnum::REJECTED->value]);

        $entity = $this->makeEntity($certificateRequest, InternalState::FAILED);

        $this->fire($entity, InternalState::POLLING, InternalState::FAILED);

        $this->assertMockExpectationsMet();
    }

    /**
     * FAILED_RECOVERABLE es recuperable: la solicitud debe permanecer en
     * PROCESSING, sin tocarse.
     */
    public function test_does_not_reject_when_internal_state_is_failed_recoverable(): void
    {
        $certificateRequest = $this->makeCertificateRequest(
            CertificateRequestStatusEnum::PROCESSING->value,
        );

        $certificateRequest->shouldNotReceive('update');

        $entity = $this->makeEntity(
            $certificateRequest,
            InternalState::FAILED_RECOVERABLE,
            'rues_error',
        );

        $this->fire($entity, InternalState::POLLING, InternalState::FAILED_RECOVERABLE);

        $this->assertSame(
            CertificateRequestStatusEnum::PROCESSING->value,
            $certificateRequest->request_status,
        );
    }

    public function test_auto_revoke_when_internal_state_is_revoked(): void
    {
        $certificateRequest = $this->makeCertificateRequest(
            CertificateRequestStatusEnum::PROCESSED->value,
        );

        $certificateRequest->shouldReceive('update')
            ->once()
            ->with(['request_status' => CertificateRequestStatusEnum::REVOKED->value]);

        $entity = $this->makeEntity($certificateRequest, InternalState::REVOKED, 'revoked');

        $this->fire($entity, InternalState::COMPLETED, InternalState::REVOKED);

        $this->assertMockExpectationsMet();
    }

    /**
     * EXPIRED (Viafirma) mapea a CANCELLED, NO a EXPIRED: la solicitud nunca
     * llegó a emitirse porque el cliente no completó el KYC. EXPIRED está
     * reservado para certificados emitidos cuya vigencia venció.
     */
    public function test_auto_expire_maps_to_cancelled_not_expired(): void
    {
        $certificateRequest = $this->makeCertificateRequest(
            CertificateRequestStatusEnum::PROCESSING->value,
        );

        $certificateRequest->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn (array $attrs) =>
                ($attrs['request_status'] ?? null) === CertificateRequestStatusEnum::CANCELLED->value
            ));

        $entity = $this->makeEntity($certificateRequest, InternalState::EXPIRED, 'accreditation');

        $this->fire($entity, InternalState::POLLING, InternalState::EXPIRED);

        $this->assertMockExpectationsMet();
    }

    /**
     * Sin solicitud asociada el listener registra un aviso y sale: no debe
     * lanzar, o abortaría la transición de estado que lo disparó.
     */
    public function test_handles_missing_certificate_request_gracefully(): void
    {
        $entity = new ViafirmaCertificateRequest();
        $entity->id = 1;

        $state = new ViafirmaCertificateRequestState();
        $state->internal_state = InternalState::FAILED;
        $state->remote_status  = 'fail';
        $entity->setRelation('state', $state);
        $entity->setRelation('certificateRequest', null);

        $this->fire($entity, InternalState::POLLING, InternalState::FAILED);

        $this->assertTrue(true, 'No debe lanzar excepción.');
    }

    /**
     * Desde un estado terminal la transición a REJECTED no está permitida:
     * el listener avisa y no actualiza nada.
     */
    public function test_logs_warning_on_invalid_transition(): void
    {
        $certificateRequest = $this->makeCertificateRequest(
            CertificateRequestStatusEnum::REVOKED->value,
        );

        $certificateRequest->shouldNotReceive('update');

        $entity = $this->makeEntity($certificateRequest, InternalState::FAILED);

        $this->fire($entity, InternalState::POLLING, InternalState::FAILED);

        $this->assertMockExpectationsMet();
    }
}
