<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendMonthlyCompanyCertificatesReportJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests unitarios para SendMonthlyCompanyCertificatesReportJob.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class SendMonthlyCompanyCertificatesReportJobTest extends TestCase
{
    public function test_el_job_se_despacha_a_la_cola_reports(): void
    {
        Queue::fake();

        SendMonthlyCompanyCertificatesReportJob::dispatch();

        Queue::assertPushedOn('reports', SendMonthlyCompanyCertificatesReportJob::class);
    }

    public function test_el_job_tiene_timeout_de_600_segundos(): void
    {
        $job = new SendMonthlyCompanyCertificatesReportJob();

        $this->assertSame(600, $job->timeout);
    }

    public function test_el_job_tiene_tres_intentos(): void
    {
        $job = new SendMonthlyCompanyCertificatesReportJob();

        $this->assertSame(3, $job->tries);
    }

    public function test_el_job_tiene_backoff_progresivo(): void
    {
        $job = new SendMonthlyCompanyCertificatesReportJob();

        $this->assertIsArray($job->backoff);
        $this->assertCount(3, $job->backoff);
        // Verificar que los backoffs son progresivos
        $this->assertLessThan($job->backoff[1], $job->backoff[0]);
        $this->assertLessThan($job->backoff[2], $job->backoff[1]);
    }

    public function test_acepta_company_id_especifico(): void
    {
        Queue::fake();

        SendMonthlyCompanyCertificatesReportJob::dispatch(42);

        Queue::assertPushed(SendMonthlyCompanyCertificatesReportJob::class);
    }

    public function test_acepta_rango_de_fechas_personalizado(): void
    {
        Queue::fake();

        $start = Carbon::create(2026, 1, 1);
        $end   = Carbon::create(2026, 1, 31);

        SendMonthlyCompanyCertificatesReportJob::dispatch(null, $start, $end);

        Queue::assertPushed(SendMonthlyCompanyCertificatesReportJob::class);
    }

    public function test_usa_inicio_del_mes_anterior_por_defecto(): void
    {
        $job = new SendMonthlyCompanyCertificatesReportJob();

        $ref   = new \ReflectionClass($job);
        $prop  = $ref->getProperty('startDate');
        $prop->setAccessible(true);

        $esperado = now()->subMonth()->startOfMonth()->startOfDay();

        $this->assertTrue($prop->getValue($job)->isSameDay($esperado));
    }

    public function test_usa_fin_del_mes_anterior_por_defecto(): void
    {
        $job = new SendMonthlyCompanyCertificatesReportJob();

        $ref  = new \ReflectionClass($job);
        $prop = $ref->getProperty('endDate');
        $prop->setAccessible(true);

        $esperado = now()->subMonth()->endOfMonth()->endOfDay();

        $this->assertTrue($prop->getValue($job)->isSameDay($esperado));
    }

    public function test_company_id_nulo_indica_procesamiento_de_todas_las_empresas(): void
    {
        $job = new SendMonthlyCompanyCertificatesReportJob(null);

        $ref  = new \ReflectionClass($job);
        $prop = $ref->getProperty('companyId');
        $prop->setAccessible(true);

        $this->assertNull($prop->getValue($job));
    }

    public function test_company_id_especifico_se_almacena_correctamente(): void
    {
        $job = new SendMonthlyCompanyCertificatesReportJob(99);

        $ref  = new \ReflectionClass($job);
        $prop = $ref->getProperty('companyId');
        $prop->setAccessible(true);

        $this->assertSame(99, $prop->getValue($job));
    }

    public function test_despachar_dos_veces_encola_dos_instancias(): void
    {
        Queue::fake();

        SendMonthlyCompanyCertificatesReportJob::dispatch(1);
        SendMonthlyCompanyCertificatesReportJob::dispatch(2);

        Queue::assertPushed(SendMonthlyCompanyCertificatesReportJob::class, 2);
    }
}
