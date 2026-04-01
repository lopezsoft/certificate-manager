<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendAdminExpiringCertificatesReportJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests unitarios para SendAdminExpiringCertificatesReportJob.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class SendAdminExpiringCertificatesReportJobTest extends TestCase
{
    public function test_el_job_se_despacha_a_la_cola_reports(): void
    {
        Queue::fake();

        SendAdminExpiringCertificatesReportJob::dispatch();

        Queue::assertPushedOn('reports', SendAdminExpiringCertificatesReportJob::class);
    }

    public function test_el_job_tiene_timeout_de_300_segundos(): void
    {
        $job = new SendAdminExpiringCertificatesReportJob();

        $this->assertSame(300, $job->timeout);
    }

    public function test_el_job_tiene_tres_intentos(): void
    {
        $job = new SendAdminExpiringCertificatesReportJob();

        $this->assertSame(3, $job->tries);
    }

    public function test_el_job_tiene_backoff_progresivo(): void
    {
        $job = new SendAdminExpiringCertificatesReportJob();

        $this->assertIsArray($job->backoff);
        $this->assertCount(3, $job->backoff);
        $this->assertLessThan($job->backoff[1], $job->backoff[0]);
        $this->assertLessThan($job->backoff[2], $job->backoff[1]);
    }

    public function test_modo_diario_por_defecto(): void
    {
        $job = new SendAdminExpiringCertificatesReportJob();

        $ref  = new \ReflectionClass($job);
        $prop = $ref->getProperty('isWeeklyReport');
        $prop->setAccessible(true);

        $this->assertFalse($prop->getValue($job));
    }

    public function test_modo_semanal_cuando_se_indica(): void
    {
        $job = new SendAdminExpiringCertificatesReportJob(true);

        $ref  = new \ReflectionClass($job);
        $prop = $ref->getProperty('isWeeklyReport');
        $prop->setAccessible(true);

        $this->assertTrue($prop->getValue($job));
    }

    public function test_despachar_reporte_diario_y_semanal_encola_dos_instancias(): void
    {
        Queue::fake();

        SendAdminExpiringCertificatesReportJob::dispatch(false); // diario
        SendAdminExpiringCertificatesReportJob::dispatch(true);  // semanal

        Queue::assertPushed(SendAdminExpiringCertificatesReportJob::class, 2);
    }

    public function test_reporte_diario_y_semanal_son_instancias_distintas(): void
    {
        $daily  = new SendAdminExpiringCertificatesReportJob(false);
        $weekly = new SendAdminExpiringCertificatesReportJob(true);

        $ref  = new \ReflectionClass(SendAdminExpiringCertificatesReportJob::class);
        $prop = $ref->getProperty('isWeeklyReport');
        $prop->setAccessible(true);

        $this->assertNotSame($prop->getValue($daily), $prop->getValue($weekly));
    }
}
