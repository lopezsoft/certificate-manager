<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Models;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Tests\TestCase;
use Tests\Unit\Modules\Viafirma\CreatesViafirmaSchemaInMemory;

/**
 * Tests para scopePendingAutoRedownload (Iniciativa 3).
 *
 * Un scope ES una consulta SQL: probarlo exige una tabla real. Se usa SQLite
 * `:memory:` con el esquema mínimo — ninguna base de datos real se toca. Las
 * filas se insertan directamente (sin factories, que no existen para estos
 * modelos).
 *
 * Verifica que el scope excluya los casos sin P7B disponible (rues_error,
 * accreditation_rejected, fail) e incluya sólo los que sí lo tienen.
 */
class ViafirmaCertificateRequestStateTest extends TestCase
{
    use CreatesViafirmaSchemaInMemory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createViafirmaRequestTables();
    }

    /**
     * Inserta un estado con los atributos dados y devuelve su id.
     */
    private function makeState(
        RemoteStatus $remoteStatus,
        int          $attempts = 0,
        int          $minutesAgo = 5,
        InternalState $internalState = InternalState::FAILED_RECOVERABLE,
    ): int {
        $state = new ViafirmaCertificateRequestState();
        $state->viafirma_certificate_request_id = random_int(1, PHP_INT_MAX);
        $state->internal_state           = $internalState;
        $state->remote_status            = $remoteStatus->value;
        $state->auto_redownload_attempts = $attempts;
        $state->save();

        // `updated_at` se fija después: el save() lo sobrescribe con now().
        ViafirmaCertificateRequestState::where('id', $state->id)
            ->update(['updated_at' => now()->subMinutes($minutesAgo)]);

        return $state->id;
    }

    /** @return int[] ids devueltos por el scope */
    private function pendingIds(): array
    {
        return ViafirmaCertificateRequestState::pendingAutoRedownload()
            ->pluck('id')
            ->all();
    }

    // ── Exclusiones: estados remotos SIN P7B disponible ───────────────────

    public function test_scope_pending_auto_redownload_excludes_rues_error(): void
    {
        $id = $this->makeState(RemoteStatus::RUES_ERROR);

        $this->assertNotContains($id, $this->pendingIds());
    }

    public function test_scope_pending_auto_redownload_excludes_accreditation_rejected(): void
    {
        $id = $this->makeState(RemoteStatus::ACCREDITATION_REJECTED);

        $this->assertNotContains($id, $this->pendingIds());
    }

    public function test_scope_pending_auto_redownload_excludes_fail(): void
    {
        $id = $this->makeState(RemoteStatus::FAIL);

        $this->assertNotContains($id, $this->pendingIds());
    }

    /** Sólo FAILED_RECOVERABLE califica: un estado terminal no se reintenta. */
    public function test_scope_pending_auto_redownload_excludes_non_recoverable_internal_state(): void
    {
        $id = $this->makeState(
            RemoteStatus::GENERATED_NOT_DOWNLOADED,
            internalState: InternalState::COMPLETED,
        );

        $this->assertNotContains($id, $this->pendingIds());
    }

    // ── Inclusiones: estados remotos CON P7B disponible ───────────────────

    public function test_scope_pending_auto_redownload_includes_generated_not_downloaded(): void
    {
        $id = $this->makeState(RemoteStatus::GENERATED_NOT_DOWNLOADED);

        $this->assertContains($id, $this->pendingIds());
    }

    public function test_scope_pending_auto_redownload_includes_generated_and_downloaded(): void
    {
        $id = $this->makeState(RemoteStatus::GENERATED_AND_DOWNLOADED);

        $this->assertContains($id, $this->pendingIds());
    }

    // ── Puertas de intentos y tiempo ──────────────────────────────────────

    public function test_scope_pending_auto_redownload_respects_max_attempts(): void
    {
        $maxAttempts = (int) config('viafirma.auto_redownload.max_attempts', 5);

        $below  = $this->makeState(RemoteStatus::GENERATED_NOT_DOWNLOADED, attempts: $maxAttempts - 1);
        $atCap  = $this->makeState(RemoteStatus::GENERATED_NOT_DOWNLOADED, attempts: $maxAttempts);

        $pending = $this->pendingIds();

        $this->assertContains($below, $pending);
        $this->assertNotContains($atCap, $pending, 'Alcanzado el tope, deja de reintentarse.');
    }

    public function test_scope_pending_auto_redownload_respects_time_gate(): void
    {
        $minWait = (int) config('viafirma.auto_redownload.min_wait_minutes', 2);

        $old    = $this->makeState(RemoteStatus::GENERATED_NOT_DOWNLOADED, minutesAgo: $minWait + 1);
        $recent = $this->makeState(RemoteStatus::GENERATED_NOT_DOWNLOADED, minutesAgo: 0);

        $pending = $this->pendingIds();

        $this->assertContains($old, $pending);
        $this->assertNotContains($recent, $pending, 'Debe esperar el tiempo mínimo antes de reintentar.');
    }
}
