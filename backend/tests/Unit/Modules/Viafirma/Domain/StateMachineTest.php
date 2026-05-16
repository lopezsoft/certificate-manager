<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Domain;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Domain\Events\ViafirmaReadyToDownload;
use App\Modules\Viafirma\Domain\Events\ViafirmaRequestFailed;
use App\Modules\Viafirma\Domain\Events\ViafirmaStatusChanged;
use App\Modules\Viafirma\Domain\StateMachine;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Support\Facades\Event;
use Psr\Log\NullLogger;
use Tests\TestCase;

/**
 * Tests unitarios del StateMachine (V-301 / V-309).
 */
class StateMachineTest extends TestCase
{
    private StateMachine $fsm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fsm = new StateMachine(new NullLogger());
    }

    // ── Transiciones válidas ──────────────────────────────────────────────

    /** @test */
    public function it_transitions_from_submitted_to_polling_on_progressing_status(): void
    {
        Event::fake();

        $entity = $this->makeEntity(InternalState::SUBMITTED);

        $changed = $this->fsm->transition($entity, RemoteStatus::RUES_CHECK, ['mock' => true]);

        $this->assertTrue($changed);
        $this->assertEquals(InternalState::POLLING, $entity->internal_state);
        $this->assertEquals('rues_check', $entity->remote_status);

        Event::assertDispatched(ViafirmaStatusChanged::class, fn ($e) =>
            $e->previousState === InternalState::SUBMITTED
            && $e->newState === InternalState::POLLING
        );
    }

    /** @test */
    public function it_transitions_to_ready_to_download_on_generated_not_downloaded(): void
    {
        Event::fake();

        $entity = $this->makeEntity(InternalState::POLLING, 'inProcess');

        $changed = $this->fsm->transition($entity, RemoteStatus::GENERATED_NOT_DOWNLOADED);

        $this->assertTrue($changed);
        $this->assertEquals(InternalState::READY_TO_DOWNLOAD, $entity->internal_state);

        Event::assertDispatched(ViafirmaReadyToDownload::class);
        Event::assertDispatched(ViafirmaStatusChanged::class);
    }

    /** @test */
    public function it_transitions_to_failed_recoverable_on_rues_error(): void
    {
        Event::fake();

        $entity = $this->makeEntity(InternalState::POLLING, 'rues_check');

        $this->fsm->transition($entity, RemoteStatus::RUES_ERROR);

        $this->assertEquals(InternalState::FAILED_RECOVERABLE, $entity->internal_state);
        $this->assertNotNull($entity->last_error_code);
        $this->assertNotNull($entity->last_error_message);

        Event::assertDispatched(ViafirmaRequestFailed::class);
    }

    /** @test */
    public function it_transitions_to_failed_on_terminal_fail(): void
    {
        Event::fake();

        $entity = $this->makeEntity(InternalState::POLLING, 'inProcess');

        $this->fsm->transition($entity, RemoteStatus::FAIL);

        $this->assertEquals(InternalState::FAILED, $entity->internal_state);
        Event::assertDispatched(ViafirmaRequestFailed::class);
    }

    /** @test */
    public function it_transitions_to_completed_on_generated_and_downloaded(): void
    {
        Event::fake();

        $entity = $this->makeEntity(InternalState::POLLING, 'inProcess');

        $this->fsm->transition($entity, RemoteStatus::GENERATED_AND_DOWNLOADED);

        $this->assertEquals(InternalState::COMPLETED, $entity->internal_state);
    }

    // ── Guard clauses ────────────────────────────────────────────────────

    /** @test */
    public function it_skips_transition_when_entity_is_terminal(): void
    {
        Event::fake();

        $entity = $this->makeEntity(InternalState::COMPLETED);

        $changed = $this->fsm->transition($entity, RemoteStatus::RUES_CHECK);

        $this->assertFalse($changed);
        $this->assertEquals(InternalState::COMPLETED, $entity->internal_state);

        Event::assertNotDispatched(ViafirmaStatusChanged::class);
    }

    /** @test */
    public function it_does_not_change_state_when_remote_maps_to_same_internal(): void
    {
        Event::fake();

        $entity = $this->makeEntity(InternalState::POLLING, 'rues_check');

        // Still progressing → still POLLING — no state change
        $changed = $this->fsm->transition($entity, RemoteStatus::ACCREDITATION);

        $this->assertFalse($changed);
        $this->assertEquals(InternalState::POLLING, $entity->internal_state);
        // remote_status should update even without internal change
        $this->assertEquals('accreditation', $entity->remote_status);

        Event::assertNotDispatched(ViafirmaStatusChanged::class);
    }

    // ── Manual transitions ───────────────────────────────────────────────

    /** @test */
    public function mark_failed_transitions_to_failed_state(): void
    {
        Event::fake();

        $entity = $this->makeEntity(InternalState::POLLING);

        $this->fsm->markFailed($entity, 'MAX_ATTEMPTS', 'Superado máximo de intentos.');

        $this->assertEquals(InternalState::FAILED, $entity->internal_state);
        $this->assertEquals('MAX_ATTEMPTS', $entity->last_error_code);

        Event::assertDispatched(ViafirmaRequestFailed::class);
    }

    /** @test */
    public function mark_expired_transitions_to_expired_state(): void
    {
        Event::fake();

        $entity = $this->makeEntity(InternalState::POLLING);

        $this->fsm->markExpired($entity);

        $this->assertEquals(InternalState::EXPIRED, $entity->internal_state);
        $this->assertEquals('POLL_EXPIRED', $entity->last_error_code);

        Event::assertDispatched(ViafirmaRequestFailed::class);
    }

    /** @test */
    public function mark_failed_is_noop_on_terminal_entity(): void
    {
        Event::fake();

        $entity = $this->makeEntity(InternalState::COMPLETED);

        $this->fsm->markFailed($entity, 'WHATEVER', 'noop');

        $this->assertEquals(InternalState::COMPLETED, $entity->internal_state);
        Event::assertNotDispatched(ViafirmaRequestFailed::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function makeEntity(InternalState $state, ?string $remoteStatus = null): ViafirmaCertificateRequest
    {
        $entity = new ViafirmaCertificateRequest();
        $entity->id = 1;
        $entity->certificate_request_id = 42;
        $entity->company_id = 1;
        $entity->internal_state = $state;
        $entity->remote_status = $remoteStatus;
        $entity->poll_attempts = 0;
        $entity->exists = true; // Simulate persisted

        return $entity;
    }
}
