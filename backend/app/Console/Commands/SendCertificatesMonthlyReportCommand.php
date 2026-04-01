<?php

namespace App\Console\Commands;

use App\Jobs\SendMonthlyAdminCertificatesReportJob;
use App\Jobs\SendMonthlyCompanyCertificatesReportJob;
use Illuminate\Console\Command;

class SendCertificatesMonthlyReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:monthly-report
                            {--admin-only : Enviar solo el reporte consolidado al administrador}
                            {--company-id= : ID de empresa específica (si se omite, envía a todas)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía los informes mensuales de certificados (empresas y/o administrador)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $adminOnly = $this->option('admin-only');
        $companyId = $this->option('company-id');

        if (!$adminOnly) {
            $target = $companyId
                ? "empresa ID {$companyId}"
                : 'todas las empresas';

            $this->info("Despachando informe mensual para {$target}...");
            SendMonthlyCompanyCertificatesReportJob::dispatch($companyId ? (int) $companyId : null);
            $this->info('Job de empresas despachado en la cola [reports].');
        }

        $this->info('Despachando informe mensual consolidado para el administrador...');
        SendMonthlyAdminCertificatesReportJob::dispatch();
        $this->info('Job de administrador despachado en la cola [reports].');

        $this->line('Ejecuta: php artisan queue:work --queue=reports');

        return self::SUCCESS;
    }
}
