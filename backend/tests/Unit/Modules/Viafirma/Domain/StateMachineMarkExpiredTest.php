<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Domain;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Events\ViafirmaRequestFailed;
use App\Modules\Viafirma\Domain\Events\ViafirmaStatusChanged;
use App\Modules\Viafirma\Domain\StateMachine;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Illuminate\Support\Facades\Event;
use Psr\Log\NullLogger;
use Tests\TestCase;

/**
 * Cubre StateMachine::markExpired() de forma aislada, sin depender del
 * helper makeEntity() de StateMachineTest (desactualizado — setea
 * internal_state/remote_status directo en la entidad en vez de en la
 * relación `state`, que es donde vive en el esquema actual).
 *
 * NOTA: solo se cubre aquí el guard clause de "ya es terminal, no hacer
 * nada" — el camino feliz de markExpired() hace una escritura real a
 * ViafirmaStatusHistory::create() (recordHistory(), privado, sin punto de
 * inyección para mockear) y choca con el gap ya documentado de esquema
 * SQLite en memoria ("no such table: viafirma_status_history"). Cubrir ese
 * camino requeriría tocar BD, lo cual está fuera de las reglas de este
 * proyecto para tests unitarios — queda como gap conocido, igual que
 * PollingSchedulerTest/ViafirmaRequestFailedListenerTest.
 */
final class StateMachineMarkExpiredTest extends TestCase
{
    public function test_no_hace_nada_si_ya_es_terminal(): void
    {
        Event::fake();

        $state = new ViafirmaCertificateRequestState();
        $state->internal_state = InternalState::COMPLETED;

        $entity = new ViafirmaCertificateRequest();
        $entity->id = 2;
        $entity->setRelation('state', $state);

        (new StateMachine(new SafePemLogger(new NullLogger())))->markExpired($entity);

        Event::assertNotDispatched(ViafirmaStatusChanged::class);
        Event::assertNotDispatched(ViafirmaRequestFailed::class);
        $this->assertSame(InternalState::COMPLETED, $entity->state->internal_state);
    }
}
