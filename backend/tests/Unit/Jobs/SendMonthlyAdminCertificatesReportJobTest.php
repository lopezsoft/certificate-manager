<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendMonthlyAdminCertificatesReportJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests unitarios para SendMonthlyAdminCertificatesReportJob.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class SendMonthlyAdminCertificatesReportJobTest extends TestCase
{
    public function test_el_job_se_despacha_a_la_cola_reports(): void
    {
        Queue::fake();

        SendMonthlyAdminCertificatesReportJob::dispatch();

        Queue::assertPushedOn('reports', SendMonthlyAdminCertificatesReportJob::class);
    }

    public function test_el_job_tiene_timeout_correcto(): void
    {
        $job = new SendMonthlyAdminCertificatesReportJob();

        $this->assertSame(600, $job->timeout);
    }

    public function test_el_job_tiene_tres_intentos(): void
    {
        $job = new SendMonthlyAdminCertificatesReportJob();

        $this->assertSame(3, $job->tries);
    }

    public function test_acepta_rango_de_fechas_explicito(): void
    {
        Queue::fake();

        $start = Carbon::create(2026, 1, 1);
        $end   = Carbon::create(2026, 1, 31);

        SendMonthlyAdminCertificatesReportJob::dispatch($start, $end);

        Queue::assertPushed(SendMonthlyAdminCertificatesReportJob::class);
    }

    public function test_usa_mes_anterior_cuando_no_se_especifican_fechas(): void
    {
        $job = new SendMonthlyAdminCertificatesReportJob();

        // Las fechas deben estar en el mes anterior al actual
        $expectedStart = now()->subMonth()->startOfMonth()->startOfDay();
        $expectedEnd   = now()->subMonth()->endOfMonth()->endOfDay();

        // Acceder a las propiedades mediante reflexión ya que son privadas
        $ref   = new \ReflectionClass($job);
        $start = $ref->getProperty('startDate');
        $end   = $ref->getProperty('endDate');
        $start->setAccessible(true);
        $end->setAccessible(true);

        $this->assertTrue($start->getValue($job)->isSameDay($expectedStart));
        $this->assertTrue($end->getValue($job)->isSameDay($expectedEnd));
    }
}
