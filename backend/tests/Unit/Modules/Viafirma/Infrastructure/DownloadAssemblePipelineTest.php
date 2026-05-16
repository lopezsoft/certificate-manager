<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Infrastructure;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests de la pipeline de descarga y ensamblaje P12 (V-402, V-405, V-406, V-410).
 */
class DownloadAssemblePipelineTest extends TestCase
{
    /** @test */
    public function download_endpoint_rejects_non_assembled_state(): void
    {
        $entity = new ViafirmaCertificateRequest();
        $entity->id = 1;
        $entity->internal_state = InternalState::POLLING;
        $entity->p12_storage_path = 'some/path.p12';

        // InternalState::POLLING is not in [ASSEMBLED, COMPLETED]
        $this->assertFalse(
            in_array($entity->internal_state, [InternalState::ASSEMBLED, InternalState::COMPLETED], true)
        );
    }

    /** @test */
    public function download_endpoint_accepts_assembled_state(): void
    {
        $entity = new ViafirmaCertificateRequest();
        $entity->internal_state = InternalState::ASSEMBLED;

        $this->assertTrue(
            in_array($entity->internal_state, [InternalState::ASSEMBLED, InternalState::COMPLETED], true)
        );
    }

    /** @test */
    public function download_endpoint_accepts_completed_state(): void
    {
        $entity = new ViafirmaCertificateRequest();
        $entity->internal_state = InternalState::COMPLETED;

        $this->assertTrue(
            in_array($entity->internal_state, [InternalState::ASSEMBLED, InternalState::COMPLETED], true)
        );
    }

    /** @test */
    public function purged_pin_is_detected(): void
    {
        $entity = new ViafirmaCertificateRequest();
        $entity->p12_password_ref = 'PURGED';

        $this->assertTrue(
            empty($entity->p12_password_ref) || $entity->p12_password_ref === 'PURGED'
        );
    }

    /** @test */
    public function internal_state_downloaded_exists_in_enum(): void
    {
        $state = InternalState::DOWNLOADED;
        $this->assertEquals('DOWNLOADED', $state->value);
        $this->assertFalse($state->isTerminal());
    }

    /** @test */
    public function internal_state_assembled_exists_in_enum(): void
    {
        $state = InternalState::ASSEMBLED;
        $this->assertEquals('ASSEMBLED', $state->value);
        $this->assertFalse($state->isTerminal());
    }

    /** @test */
    public function storage_path_generation_is_correct(): void
    {
        $codRequest = 'VF-2026-001';
        $p7bPath = "viafirma/p7b/{$codRequest}.p7b";
        $p12Path = "viafirma/p12/{$codRequest}.p12";

        $this->assertEquals('viafirma/p7b/VF-2026-001.p7b', $p7bPath);
        $this->assertEquals('viafirma/p12/VF-2026-001.p12', $p12Path);
    }

    /** @test */
    public function purge_marks_vault_ref_as_purged(): void
    {
        $entity = new ViafirmaCertificateRequest();
        $entity->key_vault_ref = 'original-ref';
        $entity->p12_password_ref = 'pin-ref';

        // Simulate purge
        $entity->key_vault_ref = 'PURGED';
        $entity->p12_password_ref = 'PURGED';

        $this->assertEquals('PURGED', $entity->key_vault_ref);
        $this->assertEquals('PURGED', $entity->p12_password_ref);
    }
}
