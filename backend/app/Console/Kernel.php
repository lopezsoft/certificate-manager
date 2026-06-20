<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\ExpireQuotasCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // Eventos de recepción

        // ====================================================================
        // NOTIFICACIONES DE CERTIFICADOS PRÓXIMOS A VENCER
        // ====================================================================

        /**
         * Job 1: Enviar notificaciones diarias a empresas con certificados próximos a vencer
         *
         * Frecuencia: Diario a las 8:00 AM (hora de Colombia)
         * Función: Notifica a cada empresa cuando su certificado está próximo a vencer
         * Queue: notifications
         */
        $schedule->job(new \App\Jobs\SendExpiringCertificatesNotificationsJob())
            ->dailyAt('08:00')
            ->timezone('America/Bogota')
            ->name('certificates:notify-expiring')
            ->withoutOverlapping(30) // Prevenir ejecuciones simultáneas
            ->onOneServer() // Ejecutar solo en un servidor si hay múltiples
            ->emailOutputOnFailure(env('MAIL_SUPPORT_ADDRESS', config('mail.from.address')))
            ->appendOutputTo(storage_path('logs/scheduled-certificates-notifications.log'));

        /**
         * Job 2: Enviar reporte diario consolidado al administrador
         *
         * Frecuencia: Diario a las 7:00 AM (hora de Colombia)
         * Función: Genera reporte de TODAS las empresas con certificados próximos a vencer
         * Queue: reports
         */
        $schedule->job(new \App\Jobs\SendAdminExpiringCertificatesReportJob(false))
            ->dailyAt('07:00')
            ->timezone('America/Bogota')
            ->name('certificates:admin-daily-report')
            ->withoutOverlapping(30)
            ->onOneServer()
            ->emailOutputOnFailure(env('MAIL_SUPPORT_ADDRESS', config('mail.from.address')))
            ->appendOutputTo(storage_path('logs/scheduled-certificates-admin-report.log'));

        /**
         * Job 3: Enviar reporte semanal consolidado (más detallado)
         *
         * Frecuencia: Semanal - Lunes a las 9:00 AM (hora de Colombia)
         * Función: Reporte semanal completo con análisis y estadísticas
         * Queue: reports
         */
        $schedule->job(new \App\Jobs\SendAdminExpiringCertificatesReportJob(true))
            ->weekly()
            ->mondays()
            ->at('09:00')
            ->timezone('America/Bogota')
            ->name('certificates:admin-weekly-report')
            ->withoutOverlapping(30)
            ->onOneServer()
            ->emailOutputOnFailure(env('MAIL_SUPPORT_ADDRESS', config('mail.from.address')))
            ->appendOutputTo(storage_path('logs/scheduled-certificates-admin-report.log'));

        // ====================================================================
        // INFORMES MENSUALES DE CERTIFICADOS EMITIDOS
        // ====================================================================

        /**
         * Job 4: Enviar informes mensuales a cada empresa
         *
         * Frecuencia: Mensual - Último día del mes a las 22:00 (10:00 PM)
         * Función: Envía a cada empresa un informe detallado de todos los certificados
         *          emitidos durante el mes, segmentados por estado y vigencia
         * Queue: reports
         */
        $schedule->job(new \App\Jobs\SendMonthlyCompanyCertificatesReportJob())
            ->lastDayOfMonth('22:00')
            ->timezone('America/Bogota')
            ->name('certificates:monthly-company-reports')
            ->withoutOverlapping(60)
            ->onOneServer()
            ->emailOutputOnFailure(env('MAIL_SUPPORT_ADDRESS', config('mail.from.address')))
            ->appendOutputTo(storage_path('logs/scheduled-certificates-monthly-reports.log'));

        /**
         * Job 5: Enviar informe mensual consolidado al administrador
         *
         * Frecuencia: Mensual - Último día del mes a las 23:00 (11:00 PM)
         * Función: Envía al administrador un reporte consolidado de TODOS los certificados
         *          emitidos durante el mes, agrupados por empresa, estado y vigencia
         * Queue: reports
         */
        $schedule->job(new \App\Jobs\SendMonthlyAdminCertificatesReportJob())
            ->lastDayOfMonth('23:00')
            ->timezone('America/Bogota')
            ->name('certificates:monthly-admin-report')
            ->withoutOverlapping(60)
            ->onOneServer()
            ->emailOutputOnFailure(env('MAIL_SUPPORT_ADDRESS', config('mail.from.address')))
            ->appendOutputTo(storage_path('logs/scheduled-certificates-monthly-reports.log'));

        // ====================================================================
        // CUPOS DE CERTIFICADOS
        // ====================================================================

        /**
         * Job 6: Expirar cupos POSTPAID vencidos
         *
         * Frecuencia: Diario a las 00:05 AM (hora de Colombia)
         */
        $schedule->command('quotas:expire')
            ->dailyAt('00:05')
            ->timezone('America/Bogota')
            ->name('quotas:expire-daily')
            ->withoutOverlapping(5)
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduled-quotas-expire.log'));

        // ====================================================================
        // VIAFIRMA POLLING WATCHDOG
        // ====================================================================

        /**
         * Job 7: Revivir solicitudes Viafirma huérfanas (sin poll programado)
         *
         * Frecuencia: Cada 5 minutos
         * Función: Busca solicitudes en estado SUBMITTED/POLLING sin next_poll_at
         *          programado y las re-arma despachando PollViafirmaStatusJob
         */
        $schedule->job(new \App\Modules\Viafirma\Infrastructure\Jobs\ReviveStalledViafirmaPollsJob())
            ->everyFiveMinutes()
            ->timezone('America/Bogota')
            ->name('viafirma:revive-stalled-polls')
            ->withoutOverlapping(10)
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduled-viafirma-watchdog.log'));

        /**
         * Job 7.5: Reintentar emisiones estancadas
         *
         * Frecuencia: Cada minuto
         * Función: Busca solicitudes de Viafirma en estado PROCESSING sin
         *          registro remoto tras 3 minutos de gracia, y las reintenta.
         *          La gracia de 3 min cubre los 3 reintentos del AutoIssueViafirmaJob
         *          (backoff=30s c/u → ~90s total) antes de declarar la emisión estancada.
         */
        $schedule->job(new \App\Jobs\Certificate\RetryStalledIssuancesJob())
            ->everyMinute()
            ->timezone('America/Bogota')
            ->name('viafirma:retry-stalled-issuances')
            ->withoutOverlapping(2)
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduled-viafirma-retry-issuances.log'));

        // ====================================================================
        // VIAFIRMA AUTO RE-DESCARGA
        // ====================================================================

        /**
         * Job 9: Re-descarga automática de certificados Viafirma en FAILED_RECOVERABLE
         *
         * Frecuencia: Cada 5 minutos
         * Función: Detecta certificados cuyo estado remoto es Generated_And_Downloaded
         *          pero el estado interno es FAILED_RECOVERABLE, y los re-descarga
         *          automáticamente reutilizando RedownloadCertificateUseCase.
         *          Máximo 5 intentos automáticos; superado ese límite, el candidato
         *          requiere intervención manual del ADMIN vía endpoint redownload.
         * Queue: default
         */
        $schedule->job(new \App\Modules\Viafirma\Infrastructure\Jobs\AutoRedownloadPendingViafirmaJob())
            ->everyFiveMinutes()
            ->timezone('America/Bogota')
            ->name('viafirma:auto-redownload-pending')
            ->withoutOverlapping(10)
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduled-viafirma-auto-redownload.log'));

        /**
         * Job 8: Purga segura de llaves privadas expiradas (Sprint 4)
         *
         * Frecuencia: Diaria a las 02:00 COT
         * Función: Destruye key_vault_ref y p12_password_ref de solicitudes
         *          terminales (COMPLETED/FAILED/EXPIRED) tras 72h de retención
         */
        $schedule->job(new \App\Modules\Viafirma\Infrastructure\Jobs\PurgeExpiredKeysJob())
            ->dailyAt('02:00')
            ->timezone('America/Bogota')
            ->name('viafirma:purge-expired-keys')
            ->withoutOverlapping(10)
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduled-viafirma-purge.log'));

        // Job 10: Revocación Comercial Automática (Fase 3)
        $schedule->job(new \App\Modules\Viafirma\Infrastructure\Jobs\AutoRevokeUnpaidCertificatesJob())
            ->dailyAt('03:00')
            ->timezone('America/Bogota')
            ->name('viafirma:auto-revoke')
            ->withoutOverlapping(10)
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduled-viafirma-auto-revoke.log'));

        // Job 11: Marcar expiración técnica/comercial (Fase 3)
        $schedule->job(new \App\Modules\Viafirma\Infrastructure\Jobs\MarkExpiredCertificatesJob())
            ->dailyAt('04:00')
            ->timezone('America/Bogota')
            ->name('viafirma:mark-expired')
            ->withoutOverlapping(10)
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduled-viafirma-mark-expired.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
