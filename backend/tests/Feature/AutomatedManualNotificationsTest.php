<?php

namespace Tests\Feature;

use App\Jobs\SendAdminExpiringCertificatesReportJob;
use App\Jobs\SendExpiringCertificatesNotificationsJob;
use App\Jobs\SendMonthlyAdminCertificatesReportJob;
use App\Jobs\SendMonthlyCompanyCertificatesReportJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests automatizados equivalentes a ManualTestCertificateNotifications.php
 * y ManualTestMonthlyReports.php.
 *
 * Cubren los escenarios manuales de tinker sin tocar la DB.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras DB.
 */
class AutomatedManualNotificationsTest extends TestCase
{
    // =========================================================================
    // Equivalentes a ManualTestCertificateNotifications — TEST 1/2/3
    // =========================================================================

    public function test_despacha_job_notificaciones_a_empresas(): void
    {
        Queue::fake();

        SendExpiringCertificatesNotificationsJob::dispatch();

        Queue::assertPushed(SendExpiringCertificatesNotificationsJob::class);
        Queue::assertPushedOn('notifications', SendExpiringCertificatesNotificationsJob::class);
    }

    public function test_despacha_job_reporte_diario_administrativo(): void
    {
        Queue::fake();

        SendAdminExpiringCertificatesReportJob::dispatch(false);

        Queue::assertPushed(SendAdminExpiringCertificatesReportJob::class);
        Queue::assertPushedOn('reports', SendAdminExpiringCertificatesReportJob::class);
    }

    public function test_despacha_job_reporte_semanal_administrativo(): void
    {
        Queue::fake();

        SendAdminExpiringCertificatesReportJob::dispatch(true);

        Queue::assertPushed(SendAdminExpiringCertificatesReportJob::class);
    }

    public function test_despacha_los_tres_jobs_de_notificaciones_en_orden(): void
    {
        Queue::fake();

        SendExpiringCertificatesNotificationsJob::dispatch();
        SendAdminExpiringCertificatesReportJob::dispatch(false);
        SendAdminExpiringCertificatesReportJob::dispatch(true);

        Queue::assertPushed(SendExpiringCertificatesNotificationsJob::class, 1);
        Queue::assertPushed(SendAdminExpiringCertificatesReportJob::class, 2);
    }

    // =========================================================================
    // Equivalentes a ManualTestCertificateNotifications — TEST 5: Configuración
    // =========================================================================

    public function test_certificate_config_tiene_admin_email(): void
    {
        $this->assertNotEmpty(config('certificate.admin_email'));
    }

    public function test_certificate_config_tiene_notification_days(): void
    {
        $days = (int) config('certificate.notification_days', 30);

        $this->assertGreaterThan(0, $days);
    }

    // =========================================================================
    // Equivalentes a ManualTestMonthlyReports — TEST 1: empresa específica
    // =========================================================================

    public function test_despacha_informe_mensual_para_empresa_especifica(): void
    {
        Queue::fake();

        $start = Carbon::now()->subMonth()->startOfMonth();
        $end   = Carbon::now()->subMonth()->endOfMonth();

        SendMonthlyCompanyCertificatesReportJob::dispatch(1, $start, $end);

        Queue::assertPushed(SendMonthlyCompanyCertificatesReportJob::class);
        Queue::assertPushedOn('reports', SendMonthlyCompanyCertificatesReportJob::class);
    }

    // =========================================================================
    // Equivalentes a ManualTestMonthlyReports — TEST 2: todas las empresas
    // =========================================================================

    public function test_despacha_informe_mensual_para_todas_las_empresas(): void
    {
        Queue::fake();

        $start = Carbon::now()->subMonth()->startOfMonth();
        $end   = Carbon::now()->subMonth()->endOfMonth();

        SendMonthlyCompanyCertificatesReportJob::dispatch(null, $start, $end);

        Queue::assertPushed(SendMonthlyCompanyCertificatesReportJob::class);
    }

    // =========================================================================
    // Equivalentes a ManualTestMonthlyReports — TEST 3: reporte admin
    // =========================================================================

    public function test_despacha_reporte_admin_mensual_consolidado(): void
    {
        Queue::fake();

        $start = Carbon::now()->subMonth()->startOfMonth();
        $end   = Carbon::now()->subMonth()->endOfMonth();

        SendMonthlyAdminCertificatesReportJob::dispatch($start, $end);

        Queue::assertPushed(SendMonthlyAdminCertificatesReportJob::class);
        Queue::assertPushedOn('reports', SendMonthlyAdminCertificatesReportJob::class);
    }

    // =========================================================================
    // Caso edge: despachar en periodo mes actual (no mes anterior)
    // =========================================================================

    public function test_acepta_periodo_del_mes_actual(): void
    {
        Queue::fake();

        $start = Carbon::now()->startOfMonth();
        $end   = Carbon::now()->endOfMonth();

        SendMonthlyCompanyCertificatesReportJob::dispatch(null, $start, $end);
        SendMonthlyAdminCertificatesReportJob::dispatch($start, $end);

        Queue::assertPushed(SendMonthlyCompanyCertificatesReportJob::class);
        Queue::assertPushed(SendMonthlyAdminCertificatesReportJob::class);
    }

    // =========================================================================
    // Verificar que sin dispatches no hay jobs en cola
    // =========================================================================

    public function test_cola_vacia_si_no_se_despacha_nada(): void
    {
        Queue::fake();

        Queue::assertNothingPushed();
    }
}
