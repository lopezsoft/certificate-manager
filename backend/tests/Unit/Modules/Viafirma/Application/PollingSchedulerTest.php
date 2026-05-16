<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Application;

use App\Modules\Viafirma\Application\Services\PollingScheduler;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Tests del PollingScheduler con Carbon::setTestNow (V-302 / V-309).
 */
class PollingSchedulerTest extends TestCase
{
    private PollingScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = new PollingScheduler();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset time travel
        parent::tearDown();
    }

    // ── nextDelay ─────────────────────────────────────────────────────────

    /** @test */
    public function next_delay_returns_base_interval_for_zero_attempts(): void
    {
        $entity = $this->makeEntity('rues_check', 0);

        $delay = $this->scheduler->nextDelay($entity);

        // base = 30, growth = 2^0 = 1, delay = 30 ± jitter
        $this->assertGreaterThanOrEqual(10, $delay);
        $this->assertLessThanOrEqual(60, $delay); // 30 * 1 + 20% jitter max
    }

    /** @test */
    public function next_delay_doubles_every_5_attempts(): void
    {
        $entity0  = $this->makeEntity('accreditation', 0);
        $entity5  = $this->makeEntity('accreditation', 5);
        $entity10 = $this->makeEntity('accreditation', 10);

        // Run multiple times to account for jitter
        $delays0  = array_map(fn () => $this->scheduler->nextDelay($entity0), range(1, 20));
        $delays5  = array_map(fn () => $this->scheduler->nextDelay($entity5), range(1, 20));
        $delays10 = array_map(fn () => $this->scheduler->nextDelay($entity10), range(1, 20));

        $avg0  = array_sum($delays0) / count($delays0);
        $avg5  = array_sum($delays5) / count($delays5);
        $avg10 = array_sum($delays10) / count($delays10);

        // avg5 should be approximately 2x avg0 (base*2 vs base*1)
        $this->assertGreaterThan($avg0 * 1.5, $avg5, 'After 5 attempts, delay should roughly double');
        // avg10 should be approximately 4x avg0 (base*4 vs base*1)
        $this->assertGreaterThan($avg0 * 3, $avg10, 'After 10 attempts, delay should be ~4x');
    }

    /** @test */
    public function next_delay_caps_growth_at_8x(): void
    {
        $entity = $this->makeEntity('rues_check', 100); // way past growth cap

        $delay = $this->scheduler->nextDelay($entity);

        // base=30, growth capped at 8 → 240 ± 20% jitter = [192, 288]
        $this->assertGreaterThanOrEqual(190, $delay);
        $this->assertLessThanOrEqual(300, $delay);
    }

    /** @test */
    public function next_delay_uses_default_for_unknown_remote_status(): void
    {
        $entity = $this->makeEntity('unknown_state_xyz', 0);

        $delay = $this->scheduler->nextDelay($entity);

        // default = 180, ± 20% jitter = [144, 216]
        $this->assertGreaterThanOrEqual(100, $delay);
        $this->assertLessThanOrEqual(250, $delay);
    }

    // ── retryAfter ────────────────────────────────────────────────────────

    /** @test */
    public function retry_after_returns_short_delay(): void
    {
        $entity = $this->makeEntity('rues_check', 0);

        $delay = $this->scheduler->retryAfter($entity);

        $this->assertGreaterThanOrEqual(15, $delay);
        $this->assertLessThanOrEqual(60, $delay);
    }

    // ── SLA / Max attempts ───────────────────────────────────────────────

    /** @test */
    public function has_exceeded_max_attempts_detects_threshold(): void
    {
        $entity = $this->makeEntity('accreditation', 95);
        $this->assertFalse($this->scheduler->hasExceededMaxAttempts($entity));

        $entity->poll_attempts = 96;
        $this->assertTrue($this->scheduler->hasExceededMaxAttempts($entity));
    }

    /** @test */
    public function has_exceeded_sla_respects_72_hour_window(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $entity = $this->makeEntity('accreditation', 10);
        $entity->submitted_at = Carbon::parse('2026-06-01 10:00:00'); // 2h ago

        $this->assertFalse($this->scheduler->hasExceededSla($entity));

        // Jump 73 hours ahead
        Carbon::setTestNow('2026-06-04 11:00:00');
        $this->assertTrue($this->scheduler->hasExceededSla($entity));
    }

    /** @test */
    public function has_exceeded_sla_returns_false_when_submitted_at_is_null(): void
    {
        $entity = $this->makeEntity('accreditation', 10);
        $entity->submitted_at = null;

        $this->assertFalse($this->scheduler->hasExceededSla($entity));
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function makeEntity(string $remoteStatus, int $attempts): ViafirmaCertificateRequest
    {
        $entity = new ViafirmaCertificateRequest();
        $entity->id = 1;
        $entity->internal_state = InternalState::POLLING;
        $entity->remote_status = $remoteStatus;
        $entity->poll_attempts = $attempts;
        return $entity;
    }
}
