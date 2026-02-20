<?php

namespace App\Console\Commands;

use App\Jobs\SendAdminExpiringCertificatesReportJob;
use Illuminate\Console\Command;

class SendCertificatesAdminReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:admin-report
                            {--weekly : Enviar el reporte semanal (más detallado) en lugar del diario}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía el reporte de certificados próximos a vencer al administrador';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isWeekly = $this->option('weekly');
        $type     = $isWeekly ? 'semanal' : 'diario';

        $this->info("Despachando reporte {$type} para el administrador...");

        SendAdminExpiringCertificatesReportJob::dispatch($isWeekly);

        $adminEmail = config('certificate.admin_email', 'N/A');
        $this->info("Job despachado correctamente en la cola [reports].");
        $this->line("Destinatario: {$adminEmail}");
        $this->line('Ejecuta: php artisan queue:work --queue=reports');

        return self::SUCCESS;
    }
}
