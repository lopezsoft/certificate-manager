<?php

namespace App\Console\Commands;

use App\Jobs\SendExpiringCertificatesNotificationsJob;
use Illuminate\Console\Command;

class NotifyExpiringCertificatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:notify-expiring
                            {--dry-run : Simula el proceso sin enviar notificaciones}
                            {--days=30 : Días de antelación para notificar (por defecto 30)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía notificaciones a empresas con certificados próximos a vencer';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Simulando proceso — no se enviarán notificaciones');
            $this->previewExpiring($days);
            return self::SUCCESS;
        }

        $this->info("Despachando job de notificaciones (próximos {$days} días)...");

        // Respetar la configuración de días si se pasa diferente al default
        if ($days !== 30) {
            config(['certificate.notification_days' => $days]);
        }

        SendExpiringCertificatesNotificationsJob::dispatch();

        $this->info('Job despachado correctamente en la cola [notifications].');
        $this->line('Ejecuta: php artisan queue:work --queue=notifications');

        return self::SUCCESS;
    }

    /**
     * Previsualizar certificados que serían notificados sin enviar emails.
     */
    private function previewExpiring(int $days): void
    {
        $threshold = now()->addDays($days);

        $certificates = \App\Models\CertificateRequest::with(['company'])
            ->whereNotNull('expiration_date')
            ->where('request_status', 'PROCESSED')
            ->where('expiration_date', '>', now())
            ->where('expiration_date', '<=', $threshold)
            ->whereHas('company', fn($q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->orderBy('expiration_date', 'asc')
            ->get();

        if ($certificates->isEmpty()) {
            $this->info("No hay certificados que vencen en los próximos {$days} días.");
            return;
        }

        $this->info("Certificados que serían notificados ({$certificates->count()}):");

        $rows = $certificates->map(function ($cert) {
            $daysLeft = now()->diffInDays(\Carbon\Carbon::parse($cert->expiration_date), false);
            $urgency  = match (true) {
                $daysLeft <= 7  => 'CRÍTICO',
                $daysLeft <= 15 => 'ALTO',
                default         => 'MEDIO',
            };

            return [
                $cert->id,
                $cert->company_name,
                $cert->company->email ?? '—',
                $cert->expiration_date,
                $daysLeft,
                $urgency,
            ];
        })->toArray();

        $this->table(
            ['ID', 'Empresa', 'Email', 'Vencimiento', 'Días', 'Urgencia'],
            $rows
        );
    }
}
