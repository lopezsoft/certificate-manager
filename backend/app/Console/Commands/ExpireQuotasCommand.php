<?php

namespace App\Console\Commands;

use App\Services\QuotaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * ExpireQuotasCommand
 *
 * Marca como EXPIRED los cupos POSTPAID cuyo period_end ya pasó.
 * Se ejecuta diariamente vía scheduler (ver Kernel).
 *
 * Uso manual: php artisan quotas:expire
 */
class ExpireQuotasCommand extends Command
{
    protected $signature   = 'quotas:expire';
    protected $description = 'Expira cupos POSTPAID con fecha de fin vencida';

    public function handle(QuotaService $quotaService): int
    {
        $this->info('Iniciando expiración de cupos...');

        $count = $quotaService->expireQuotas();

        $this->info("Cupos expirados: {$count}");
        Log::info("[QUOTAS] Expiración diaria ejecutada. Cupos expirados: {$count}");

        return self::SUCCESS;
    }
}
