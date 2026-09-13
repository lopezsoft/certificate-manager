<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Domain;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Domain\Events\ViafirmaAccreditationReached;
use App\Modules\Viafirma\Domain\StateMachine;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Tests\TestCase;
use Tests\Unit\Modules\Viafirma\CreatesViafirmaSchemaInMemory;

/**
 * ViafirmaAccreditationReached es el evento que dispara la captura del link
 * KYC y el aviso a la empresa maestra. Debe emitirse al ENTRAR en
 * `accreditation`, aunque el internal_state siga siendo POLLING (varios
 * remote_status distintos comparten ese internal_state).
 *
 * Sin BD: entidades en memoria. La única tabla creada es
 * viafirma_status_history, porque StateMachine::recordHistory() escribe
 * directo y es privado.
 */
final class StateMachineAccreditationTest extends TestCase
{
    use CreatesViafirmaSchemaInMemory;

    private StateMachine $fsm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createViafirmaStatusHistoryTable();

        $this->fsm = new StateMachine(new SafePemLogger(new NullLogger()));
    }

    private function makeEntity(string $remoteStatus, int $pollAttempts): ViafirmaCertificateRequest
    {
        $entity = new ViafirmaCertificateRequest();
        $entity->id = 1;
        $entity->certificate_request_id = 42;
        $entity->exists = true;

        $state = new ViafirmaCertificateRequestState();
        $state->internal_state = InternalState::POLLING;
        $state->remote_status  = $remoteStatus;
        $state->poll_attempts  = $pollAttempts;
        $state->exists = true;

        $entity->setRelation('state', $state);

        return $entity;
    }

    #[Test]
    public function dispara_accreditation_reached_al_entrar_en_accreditation(): void
    {
        Event::fake();

        $entity = $this->makeEntity(RemoteStatus::RUES_CHECK->value, 1);

        // rues_check -> accreditation (ambos mapean a POLLING)
        $stateChanged = $this->fsm->transition($entity, RemoteStatus::ACCREDITATION, [
            'code' => 'accreditation',
        ]);

        // El internal_state no cambia, pero el evento debe emitirse igual.
        $this->assertFalse($stateChanged);

        Event::assertDispatched(
            ViafirmaAccreditationReached::class,
            fn ($event) => $event->entity->id === $entity->id,
        );
    }

    #[Test]
    public function no_dispara_accreditation_reached_cuando_ya_estaba_en_accreditation(): void
    {
        Event::fake();

        $entity = $this->makeEntity(RemoteStatus::ACCREDITATION->value, 2);

        // accreditation -> accreditation: no es una ENTRADA, no debe reemitir
        // (evitaría reenviar el correo del link KYC en cada poll).
        $this->fsm->transition($entity, RemoteStatus::ACCREDITATION, [
            'code' => 'accreditation',
        ]);

        Event::assertNotDispatched(ViafirmaAccreditationReached::class);
    }

    #[Test]
    public function dispara_accreditation_reached_incluso_si_internal_state_no_cambia(): void
    {
        Event::fake();

        $entity = $this->makeEntity(RemoteStatus::PROPOSED_TO_ACCEPTANCE->value, 3);

        $this->fsm->transition($entity, RemoteStatus::ACCREDITATION, [
            'code' => 'accreditation',
        ]);

        Event::assertDispatched(
            ViafirmaAccreditationReached::class,
            fn ($event) => $event->entity->id === $entity->id,
        );

        // El remote_status debe quedar actualizado en memoria aunque el
        // internal_state no se mueva.
        $this->assertSame(
            RemoteStatus::ACCREDITATION->value,
            $entity->state->remote_status,
        );
    }
}
