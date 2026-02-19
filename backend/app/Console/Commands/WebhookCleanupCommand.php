<?php

namespace App\Console\Commands;

use App\Webhooks\Models\WebhookDelivery;
use Illuminate\Console\Command;

class WebhookCleanupCommand extends Command
{
    protected $signature   = 'webhook:cleanup {--days= : Días de retención (default: config webhooks.delivery_log_retention_days)}';
    protected $description = 'Elimina los logs de entrega de webhooks más antiguos del período de retención';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('webhooks.delivery_log_retention_days', 30));

        $deleted = WebhookDelivery::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Limpieza completada: {$deleted} registros eliminados (retención: {$days} días).");

        return self::SUCCESS;
    }
}
