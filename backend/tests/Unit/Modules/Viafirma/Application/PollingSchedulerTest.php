<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Application;

use App\Modules\Viafirma\Application\Services\PollingScheduler;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Tests del PollingScheduler (V-302 / V-309).
 *
 * OJO: la estrategia es INTERVALO FIJO, no backoff exponencial. El proceso de
 * acreditación en Viafirma puede tardar de minutos a horas, así que se consulta
 * cada `VIAFIRMA_POLL_INTERVAL` segundos (default 60) hasta que resuelva. Los
 * tests previos describían un backoff con jitter que fue retirado del diseño.
 *
 * Sin BD: entidades en memoria con setRelation().
 */
class PollingSchedulerTest extends TestCase
{
    private PollingScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        // Valores explícitos: el scheduler los lee en el constructor, así que
        // hay que fijarlos ANTES de instanciarlo.
        config([
            'viafirma.polling.interval_seconds'          => 60,
            'viafirma.polling.max_attempts'              => 288,
            'viafirma.polling.expiration_hours'          => 96,
            'viafirma.polling.recovery_interval_seconds' => 300,
        ]);

        $this->scheduler = new PollingScheduler();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset time travel
        parent::tearDown();
    }

    // ── nextDelay ─────────────────────────────────────────────────────────

    /** @test */
    public function next_delay_returns_the_fixed_interval(): void
    {
        $entity = $this->makeEntity('rues_check', 0);

        $this->assertSame(60, $this->scheduler->nextDelay($entity));
    }

    /** @test */
    public function next_delay_does_not_grow_with_attempts(): void
    {
        $first = $this->scheduler->nextDelay($this->makeEntity('accreditation', 0));
        $later = $this->scheduler->nextDelay($this->makeEntity('accreditation', 100));

        $this->assertSame(
            $first,
            $later,
            'El intervalo es fijo por diseño: no debe crecer con los intentos.',
        );
    }

    /** @test */
    public function next_delay_is_the_same_for_any_remote_status(): void
    {
        $known   = $this->scheduler->nextDelay($this->makeEntity('rues_check', 0));
        $unknown = $this->scheduler->nextDelay($this->makeEntity('unknown_state_xyz', 0));

        $this->assertSame($known, $unknown);
    }

    /** @test */
    public function interval_honours_configuration(): void
    {
        config(['viafirma.polling.interval_seconds' => 120]);
        $scheduler = new PollingScheduler();

        $this->assertSame(120, $scheduler->nextDelay($this->makeEntity('rues_check', 0)));
    }

    /** @test */
    public function interval_has_a_floor_of_ten_seconds(): void
    {
        config(['viafirma.polling.interval_seconds' => 1]);
        $scheduler = new PollingScheduler();

        $this->assertSame(
            10,
            $scheduler->nextDelay($this->makeEntity('rues_check', 0)),
            'Un intervalo por debajo de 10s martillearía al proveedor.',
        );
    }

    // ── retryAfter / recoveryDelay ────────────────────────────────────────

    /** @test */
    public function retry_after_uses_the_base_interval(): void
    {
        $this->assertSame(60, $this->scheduler->retryAfter($this->makeEntity('rues_check', 0)));
    }

    /** @test */
    public function recovery_delay_is_longer_than_the_base_interval(): void
    {
        $entity = $this->makeEntity('rues_error', 3);

        $recovery = $this->scheduler->recoveryDelay($entity);

        $this->assertSame(300, $recovery);
        $this->assertGreaterThan(
            $this->scheduler->nextDelay($entity),
            $recovery,
            'FAILED_RECOVERABLE espera más para dar margen al operador.',
        );
    }

    // ── SLA / Max attempts ───────────────────────────────────────────────

    /** @test */
    public function has_exceeded_max_attempts_detects_threshold(): void
    {
        $entity = $this->makeEntity('accreditation', 287);
        $this->assertFalse($this->scheduler->hasExceededMaxAttempts($entity));

        $entity->state->poll_attempts = 288;
        $this->assertTrue($this->scheduler->hasExceededMaxAttempts($entity));
    }

    /** @test */
    public function has_exceeded_sla_respects_the_configured_window(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $entity = $this->makeEntity('accreditation', 10);
        $entity->state->submitted_at = Carbon::parse('2026-06-01 10:00:00'); // 2h atrás

        $this->assertFalse($this->scheduler->hasExceededSla($entity));

        // 96h de ventana → saltamos más allá
        Carbon::setTestNow('2026-06-05 11:00:00');
        $this->assertTrue($this->scheduler->hasExceededSla($entity));
    }

    /** @test */
    public function has_exceeded_sla_returns_false_when_submitted_at_is_null(): void
    {
        $entity = $this->makeEntity('accreditation', 10);
        $entity->state->submitted_at = null;

        $this->assertFalse($this->scheduler->hasExceededSla($entity));
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Entidad en memoria. Los campos de polling viven en la tabla de estados
     * normalizada, así que se monta la relación en vez de asignar atributos.
     */
    private function makeEntity(string $remoteStatus, int $attempts): ViafirmaCertificateRequest
    {
        $entity = new ViafirmaCertificateRequest();
        $entity->id = 1;

        $state = new ViafirmaCertificateRequestState();
        $state->internal_state = InternalState::POLLING;
        $state->remote_status  = $remoteStatus;
        $state->poll_attempts  = $attempts;

        $entity->setRelation('state', $state);

        return $entity;
    }
}
