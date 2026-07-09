<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Models;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Tests\TestCase;

/**
 * Tests para scopePendingAutoRedownload (Iniciativa 3).
 *
 * Verifica que el scope excluya correctamente casos sin P7B (rues_error, etc.)
 * e incluya solo casos con P7B disponible (GENERATED_NOT_DOWNLOADED, GENERATED_AND_DOWNLOADED).
 */
class ViafirmaCertificateRequestStateTest extends TestCase
{
    /**
     * El scope debe excluir casos con remote_status = rues_error.
     */
    public function test_scope_pending_auto_redownload_excludes_rues_error(): void
    {
        // Crear un caso con FAILED_RECOVERABLE + rues_error (sin P7B)
        $viafirmaRequest = ViafirmaCertificateRequest::factory()->create();
        $state = ViafirmaCertificateRequestState::factory()->create([
            'viafirma_certificate_request_id' => $viafirmaRequest->id,
            'internal_state'                  => InternalState::FAILED_RECOVERABLE->value,
            'remote_status'                   => RemoteStatus::RUES_ERROR->value,
            'updated_at'                      => now()->subMinutes(5), // > 2 min
            'auto_redownload_attempts'        => 0,
        ]);

        // El scope NO debe incluir este caso
        $results = ViafirmaCertificateRequestState::pendingAutoRedownload()->get();
        $this->assertFalse($results->contains('id', $state->id));
    }

    /**
     * El scope debe excluir casos con remote_status = accreditation_rejected.
     */
    public function test_scope_pending_auto_redownload_excludes_accreditation_rejected(): void
    {
        $viafirmaRequest = ViafirmaCertificateRequest::factory()->create();
        $state = ViafirmaCertificateRequestState::factory()->create([
            'viafirma_certificate_request_id' => $viafirmaRequest->id,
            'internal_state'                  => InternalState::FAILED_RECOVERABLE->value,
            'remote_status'                   => RemoteStatus::ACCREDITATION_REJECTED->value,
            'updated_at'                      => now()->subMinutes(5),
            'auto_redownload_attempts'        => 0,
        ]);

        $results = ViafirmaCertificateRequestState::pendingAutoRedownload()->get();
        $this->assertFalse($results->contains('id', $state->id));
    }

    /**
     * El scope debe excluir casos con remote_status = fail.
     */
    public function test_scope_pending_auto_redownload_excludes_fail(): void
    {
        $viafirmaRequest = ViafirmaCertificateRequest::factory()->create();
        $state = ViafirmaCertificateRequestState::factory()->create([
            'viafirma_certificate_request_id' => $viafirmaRequest->id,
            'internal_state'                  => InternalState::FAILED_RECOVERABLE->value,
            'remote_status'                   => RemoteStatus::FAIL->value,
            'updated_at'                      => now()->subMinutes(5),
            'auto_redownload_attempts'        => 0,
        ]);

        $results = ViafirmaCertificateRequestState::pendingAutoRedownload()->get();
        $this->assertFalse($results->contains('id', $state->id));
    }

    /**
     * El scope DEBE incluir casos con remote_status = GENERATED_NOT_DOWNLOADED.
     */
    public function test_scope_pending_auto_redownload_includes_generated_not_downloaded(): void
    {
        $viafirmaRequest = ViafirmaCertificateRequest::factory()->create();
        $state = ViafirmaCertificateRequestState::factory()->create([
            'viafirma_certificate_request_id' => $viafirmaRequest->id,
            'internal_state'                  => InternalState::FAILED_RECOVERABLE->value,
            'remote_status'                   => RemoteStatus::GENERATED_NOT_DOWNLOADED->value,
            'updated_at'                      => now()->subMinutes(5),
            'auto_redownload_attempts'        => 0,
        ]);

        $results = ViafirmaCertificateRequestState::pendingAutoRedownload()->get();
        $this->assertTrue($results->contains('id', $state->id));
    }

    /**
     * El scope DEBE incluir casos con remote_status = GENERATED_AND_DOWNLOADED.
     */
    public function test_scope_pending_auto_redownload_includes_generated_and_downloaded(): void
    {
        $viafirmaRequest = ViafirmaCertificateRequest::factory()->create();
        $state = ViafirmaCertificateRequestState::factory()->create([
            'viafirma_certificate_request_id' => $viafirmaRequest->id,
            'internal_state'                  => InternalState::FAILED_RECOVERABLE->value,
            'remote_status'                   => RemoteStatus::GENERATED_AND_DOWNLOADED->value,
            'updated_at'                      => now()->subMinutes(5),
            'auto_redownload_attempts'        => 0,
        ]);

        $results = ViafirmaCertificateRequestState::pendingAutoRedownload()->get();
        $this->assertTrue($results->contains('id', $state->id));
    }

    /**
     * El scope debe respetar el límite de intentos (max 5).
     */
    public function test_scope_pending_auto_redownload_respects_max_attempts(): void
    {
        $viafirmaRequest = ViafirmaCertificateRequest::factory()->create();

        // Caso 1: 4 intentos (debe incluirse)
        $state1 = ViafirmaCertificateRequestState::factory()->create([
            'viafirma_certificate_request_id' => $viafirmaRequest->id,
            'internal_state'                  => InternalState::FAILED_RECOVERABLE->value,
            'remote_status'                   => RemoteStatus::GENERATED_NOT_DOWNLOADED->value,
            'updated_at'                      => now()->subMinutes(5),
            'auto_redownload_attempts'        => 4,
        ]);

        // Caso 2: 5 intentos (debe excluirse)
        $state2 = ViafirmaCertificateRequestState::factory()->create([
            'viafirma_certificate_request_id' => $viafirmaRequest->id,
            'internal_state'                  => InternalState::FAILED_RECOVERABLE->value,
            'remote_status'                   => RemoteStatus::GENERATED_NOT_DOWNLOADED->value,
            'updated_at'                      => now()->subMinutes(5),
            'auto_redownload_attempts'        => 5,
        ]);

        $results = ViafirmaCertificateRequestState::pendingAutoRedownload()->get();
        $this->assertTrue($results->contains('id', $state1->id));
        $this->assertFalse($results->contains('id', $state2->id));
    }

    /**
     * El scope debe respetar el tiempo mínimo (>= 2 min).
     */
    public function test_scope_pending_auto_redownload_respects_time_gate(): void
    {
        $viafirmaRequest = ViafirmaCertificateRequest::factory()->create();

        // Caso 1: 2 minutos (debe incluirse)
        $state1 = ViafirmaCertificateRequestState::factory()->create([
            'viafirma_certificate_request_id' => $viafirmaRequest->id,
            'internal_state'                  => InternalState::FAILED_RECOVERABLE->value,
            'remote_status'                   => RemoteStatus::GENERATED_NOT_DOWNLOADED->value,
            'updated_at'                      => now()->subMinutes(2),
            'auto_redownload_attempts'        => 0,
        ]);

        // Caso 2: 1 minuto (debe excluirse)
        $state2 = ViafirmaCertificateRequestState::factory()->create([
            'viafirma_certificate_request_id' => $viafirmaRequest->id,
            'internal_state'                  => InternalState::FAILED_RECOVERABLE->value,
            'remote_status'                   => RemoteStatus::GENERATED_NOT_DOWNLOADED->value,
            'updated_at'                      => now()->subMinutes(1),
            'auto_redownload_attempts'        => 0,
        ]);

        $results = ViafirmaCertificateRequestState::pendingAutoRedownload()->get();
        $this->assertTrue($results->contains('id', $state1->id));
        $this->assertFalse($results->contains('id', $state2->id));
    }
}
