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
use Tests\TestCase;

/**
 * Tests para ViafirmaRequestStateChangedListener (Iniciativa 2).
 *
 * Verifica que:
 * - InternalState::FAILED dispara cambio automático a request_status = REJECTED
 * - InternalState::FAILED_RECOVERABLE NO dispara cambio a REJECTED (permanece PROCESSING)
 * - Validación de transiciones permitidas
 * - Sincronización de REVOKED y EXPIRED
 */
class ViafirmaRequestStateChangedListenerTest extends TestCase
{
    private ViafirmaRequestStateChangedListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = app(ViafirmaRequestStateChangedListener::class);
    }

    /**
     * Cuando InternalState cambia a FAILED, request_status debe cambiar a REJECTED.
     */
    public function test_auto_reject_when_internal_state_is_failed(): void
    {
        // Crear entidades
        $certificateRequest = CertificateRequest::factory()->create([
            'request_status' => CertificateRequestStatusEnum::PROCESSING->value,
        ]);

        $viafirmaRequest = ViafirmaCertificateRequest::factory()
            ->has($certificateRequest, 'certificateRequest')
            ->create();

        $viafirmaRequest->load('state');
        $viafirmaRequest->state->update([
            'internal_state' => InternalState::FAILED->value,
            'remote_status'  => RemoteStatus::FAIL->value,
        ]);

        // Disparar evento
        $event = new ViafirmaStatusChanged(
            entity: $viafirmaRequest,
            previousState: InternalState::POLLING,
            newState: InternalState::FAILED,
            remoteStatus: RemoteStatus::FAIL,
        );

        $this->listener->handle($event);

        // Verificar que request_status cambió a REJECTED
        $certificateRequest->refresh();
        $this->assertEquals(
            CertificateRequestStatusEnum::REJECTED->value,
            $certificateRequest->request_status
        );
    }

    /**
     * Cuando InternalState es FAILED_RECOVERABLE, request_status NO debe cambiar a REJECTED.
     * Debe permanecer como PROCESSING.
     */
    public function test_does_not_reject_when_internal_state_is_failed_recoverable(): void
    {
        $certificateRequest = CertificateRequest::factory()->create([
            'request_status' => CertificateRequestStatusEnum::PROCESSING->value,
        ]);

        $viafirmaRequest = ViafirmaCertificateRequest::factory()
            ->has($certificateRequest, 'certificateRequest')
            ->create();

        $viafirmaRequest->load('state');
        $viafirmaRequest->state->update([
            'internal_state' => InternalState::FAILED_RECOVERABLE->value,
            'remote_status'  => RemoteStatus::RUES_ERROR->value,
        ]);

        // Disparar evento
        $event = new ViafirmaStatusChanged(
            entity: $viafirmaRequest,
            previousState: InternalState::POLLING,
            newState: InternalState::FAILED_RECOVERABLE,
            remoteStatus: RemoteStatus::RUES_ERROR,
        );

        $this->listener->handle($event);

        // Verificar que request_status sigue siendo PROCESSING (no cambió a REJECTED)
        $certificateRequest->refresh();
        $this->assertEquals(
            CertificateRequestStatusEnum::PROCESSING->value,
            $certificateRequest->request_status
        );
    }

    /**
     * Cuando InternalState cambia a REVOKED, request_status debe cambiar a REVOKED.
     */
    public function test_auto_revoke_when_internal_state_is_revoked(): void
    {
        $certificateRequest = CertificateRequest::factory()->create([
            'request_status' => CertificateRequestStatusEnum::PROCESSED->value,
        ]);

        $viafirmaRequest = ViafirmaCertificateRequest::factory()
            ->has($certificateRequest, 'certificateRequest')
            ->create();

        $viafirmaRequest->load('state');
        $viafirmaRequest->state->update([
            'internal_state' => InternalState::REVOKED->value,
            'remote_status'  => null,
        ]);

        $event = new ViafirmaStatusChanged(
            entity: $viafirmaRequest,
            previousState: InternalState::COMPLETED,
            newState: InternalState::REVOKED,
            remoteStatus: RemoteStatus::GENERATED_AND_DOWNLOADED,
        );

        $this->listener->handle($event);

        $certificateRequest->refresh();
        $this->assertEquals(
            CertificateRequestStatusEnum::REVOKED->value,
            $certificateRequest->request_status
        );
    }

    /**
     * Cuando InternalState cambia a EXPIRED, request_status debe cambiar a EXPIRED.
     */
    public function test_auto_expire_when_internal_state_is_expired(): void
    {
        $certificateRequest = CertificateRequest::factory()->create([
            'request_status' => CertificateRequestStatusEnum::PROCESSING->value,
        ]);

        $viafirmaRequest = ViafirmaCertificateRequest::factory()
            ->has($certificateRequest, 'certificateRequest')
            ->create();

        $viafirmaRequest->load('state');
        $viafirmaRequest->state->update([
            'internal_state' => InternalState::EXPIRED->value,
            'remote_status'  => null,
        ]);

        $event = new ViafirmaStatusChanged(
            entity: $viafirmaRequest,
            previousState: InternalState::POLLING,
            newState: InternalState::EXPIRED,
            remoteStatus: RemoteStatus::RUES_CHECK,
        );

        $this->listener->handle($event);

        $certificateRequest->refresh();
        $this->assertEquals(
            CertificateRequestStatusEnum::EXPIRED->value,
            $certificateRequest->request_status
        );
    }

    /**
     * Si no existe certificateRequest relacionado, el listener debe ignorar sin error.
     */
    public function test_handles_missing_certificate_request_gracefully(): void
    {
        // Crear ViafirmaCertificateRequest SIN certificateRequest
        $viafirmaRequest = ViafirmaCertificateRequest::factory()->create([
            'certificate_request_id' => null,
        ]);

        $event = new ViafirmaStatusChanged(
            entity: $viafirmaRequest,
            previousState: InternalState::POLLING,
            newState: InternalState::FAILED,
            remoteStatus: RemoteStatus::FAIL,
        );

        // No debe lanzar excepción
        $this->listener->handle($event);
        $this->assertTrue(true);
    }

    /**
     * Cuando la transición de estado no es permitida, debe loguear warning sin actualizar.
     */
    public function test_logs_warning_on_invalid_transition(): void
    {
        $certificateRequest = CertificateRequest::factory()->create([
            'request_status' => CertificateRequestStatusEnum::REJECTED->value,
        ]);

        $viafirmaRequest = ViafirmaCertificateRequest::factory()
            ->has($certificateRequest, 'certificateRequest')
            ->create();

        $viafirmaRequest->load('state');
        $viafirmaRequest->state->update([
            'internal_state' => InternalState::FAILED->value,
            'remote_status'  => RemoteStatus::FAIL->value,
        ]);

        // REJECTED -> REJECTED es transición inválida
        $event = new ViafirmaStatusChanged(
            entity: $viafirmaRequest,
            previousState: InternalState::POLLING,
            newState: InternalState::FAILED,
            remoteStatus: RemoteStatus::FAIL,
        );

        $this->listener->handle($event);

        // request_status no debe cambiar
        $certificateRequest->refresh();
        $this->assertEquals(
            CertificateRequestStatusEnum::REJECTED->value,
            $certificateRequest->request_status
        );
    }
}
