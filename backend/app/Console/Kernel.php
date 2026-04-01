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
        //
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
