<?php

namespace Tests\Unit\Modules\Viafirma\Domain;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Domain\Events\ViafirmaAccreditationReached;
use App\Modules\Viafirma\Domain\StateMachine;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StateMachineAccreditationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dispara_accreditation_reached_al_entrar_en_accreditation(): void
    {
        // Arrange
        Event::fake();

        $state = ViafirmaCertificateRequestState::factory()->create([
            'internal_state' => InternalState::POLLING,
            'remote_status' => RemoteStatus::RUES_CHECK->value,
            'poll_attempts' => 1,
        ]);

        $entity = ViafirmaCertificateRequest::factory()->create([
            'viafirma_certificate_request_state_id' => $state->id,
        ]);

        $fsm = new StateMachine(app(SafePemLogger::class));

        // Act — transición rues_check → accreditation (ambas en POLLING)
        $stateChanged = $fsm->transition($entity, RemoteStatus::ACCREDITATION, [
            'code' => 'accreditation',
        ]);

        // Assert
        // El internal_state no cambió (sigue POLLING), pero el evento debe dispararse
        $this->assertFalse($stateChanged);

        // Verificar que se disparó el evento ViafirmaAccreditationReached
        Event::assertDispatched(ViafirmaAccreditationReached::class, function ($event) use ($entity) {
            return $event->entity->id === $entity->id;
        });
    }

    #[Test]
    public function no_dispara_accreditation_reached_cuando_ya_estaba_en_accreditation(): void
    {
        // Arrange
        Event::fake();

        $state = ViafirmaCertificateRequestState::factory()->create([
            'internal_state' => InternalState::POLLING,
            'remote_status' => RemoteStatus::ACCREDITATION->value,
            'poll_attempts' => 2,
        ]);

        $entity = ViafirmaCertificateRequest::factory()->create([
            'viafirma_certificate_request_state_id' => $state->id,
        ]);

        $fsm = new StateMachine(app(SafePemLogger::class));

        // Act — transición accreditation → accreditation (sin cambio)
        $fsm->transition($entity, RemoteStatus::ACCREDITATION, [
            'code' => 'accreditation',
        ]);

        // Assert
        Event::assertNotDispatched(ViafirmaAccreditationReached::class);
    }

    #[Test]
    public function dispara_accreditation_reached_incluso_si_internal_state_no_cambia(): void
    {
        // Arrange
        Event::fake();

        // Múltiples progresiones dentro de POLLING que no cambian internal_state
        $state = ViafirmaCertificateRequestState::factory()->create([
            'internal_state' => InternalState::POLLING,
            'remote_status' => RemoteStatus::PROPOSED_TO_ACCEPTANCE->value,
            'poll_attempts' => 3,
        ]);

        $entity = ViafirmaCertificateRequest::factory()->create([
            'viafirma_certificate_request_state_id' => $state->id,
        ]);

        $fsm = new StateMachine(app(SafePemLogger::class));

        // Act — transición proposed_to_acceptance → accreditation (ambas en POLLING)
        $fsm->transition($entity, RemoteStatus::ACCREDITATION, [
            'code' => 'accreditation',
        ]);

        // Assert
        Event::assertDispatched(ViafirmaAccreditationReached::class, function ($event) use ($entity) {
            return $event->entity->id === $entity->id;
        });

        // Verificar que el remote_status fue actualizado
        $state->refresh();
        $this->assertEquals(RemoteStatus::ACCREDITATION->value, $state->remote_status);
    }
}
