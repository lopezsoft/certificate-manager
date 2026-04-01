<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendExpiringCertificatesNotificationsJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests unitarios para SendExpiringCertificatesNotificationsJob.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class SendExpiringCertificatesNotificationsJobTest extends TestCase
{
    public function test_el_job_se_despacha_a_la_cola_notifications(): void
    {
        Queue::fake();

        SendExpiringCertificatesNotificationsJob::dispatch();

        Queue::assertPushedOn('notifications', SendExpiringCertificatesNotificationsJob::class);
    }

    public function test_el_job_tiene_timeout_correcto(): void
    {
        $job = new SendExpiringCertificatesNotificationsJob();

        $this->assertSame(300, $job->timeout);
    }

    public function test_el_job_tiene_tres_intentos(): void
    {
        $job = new SendExpiringCertificatesNotificationsJob();

        $this->assertSame(3, $job->tries);
    }

    public function test_el_job_tiene_backoff_progresivo(): void
    {
        $job = new SendExpiringCertificatesNotificationsJob();

        $this->assertIsArray($job->backoff);
        $this->assertCount(3, $job->backoff);
    }

    public function test_dispatchar_dos_veces_encola_dos_instancias(): void
    {
        Queue::fake();

        SendExpiringCertificatesNotificationsJob::dispatch();
        SendExpiringCertificatesNotificationsJob::dispatch();

        Queue::assertPushed(SendExpiringCertificatesNotificationsJob::class, 2);
    }
}
