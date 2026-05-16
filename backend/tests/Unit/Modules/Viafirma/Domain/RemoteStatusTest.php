<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Domain;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use Tests\TestCase;

/**
 * Tests del RemoteStatus enum — cobertura de clasificación semántica.
 */
class RemoteStatusTest extends TestCase
{
    /** @test */
    public function progressing_statuses_map_to_polling_internal_state(): void
    {
        $progressing = [
            RemoteStatus::RUES_CHECK,
            RemoteStatus::ACCREDITATION,
            RemoteStatus::ACCREDITATION_CHECK,
            RemoteStatus::ACCREDITATION_COMPLETED,
            RemoteStatus::ACCREDITATION_VERIFIED,
            RemoteStatus::PROPOSE_FOR,
            RemoteStatus::PROPOSED_TO_ACCEPTANCE,
            RemoteStatus::IN_PROCESS,
            RemoteStatus::ALL_OK,
        ];

        foreach ($progressing as $status) {
            $this->assertTrue($status->isProgressing(), "{$status->value} should be progressing");
            $this->assertFalse($status->shouldStopPolling(), "{$status->value} should NOT stop polling");
            $this->assertEquals(InternalState::POLLING, $status->toInternalState());
        }
    }

    /** @test */
    public function stop_recoverable_statuses_map_to_failed_recoverable(): void
    {
        $stops = [RemoteStatus::RUES_ERROR, RemoteStatus::ACCREDITATION_REJECTED];

        foreach ($stops as $status) {
            $this->assertTrue($status->isStopRecoverable(), "{$status->value} should be stop recoverable");
            $this->assertTrue($status->shouldStopPolling());
            $this->assertEquals(InternalState::FAILED_RECOVERABLE, $status->toInternalState());
        }
    }

    /** @test */
    public function generated_not_downloaded_maps_to_ready_to_download(): void
    {
        $status = RemoteStatus::GENERATED_NOT_DOWNLOADED;

        $this->assertTrue($status->isReadyToDownload());
        $this->assertTrue($status->shouldStopPolling());
        $this->assertEquals(InternalState::READY_TO_DOWNLOAD, $status->toInternalState());
    }

    /** @test */
    public function generated_and_downloaded_maps_to_completed(): void
    {
        $status = RemoteStatus::GENERATED_AND_DOWNLOADED;

        $this->assertTrue($status->isTerminalOk());
        $this->assertTrue($status->shouldStopPolling());
        $this->assertEquals(InternalState::COMPLETED, $status->toInternalState());
    }

    /** @test */
    public function fail_maps_to_failed(): void
    {
        $status = RemoteStatus::FAIL;

        $this->assertTrue($status->isTerminalFail());
        $this->assertTrue($status->shouldStopPolling());
        $this->assertEquals(InternalState::FAILED, $status->toInternalState());
    }

    /** @test */
    public function all_cases_have_a_valid_internal_state_mapping(): void
    {
        foreach (RemoteStatus::cases() as $status) {
            $internal = $status->toInternalState();
            $this->assertInstanceOf(InternalState::class, $internal, "No mapping for {$status->value}");
        }
    }
}
