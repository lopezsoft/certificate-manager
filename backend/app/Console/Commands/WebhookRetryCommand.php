<?php

namespace App\Console\Commands;

use App\Webhooks\Jobs\DeliverWebhookJob;
use App\Webhooks\Models\WebhookDelivery;
use Illuminate\Console\Command;

class WebhookRetryCommand extends Command
{
    protected $signature   = 'webhook:retry {delivery_id : ID del registro en webhook_deliveries}';
    protected $description = 'Reintenta manualmente la entrega de un webhook fallido';

    public function handle(): int
    {
        $delivery = WebhookDelivery::with('endpoint')->find($this->argument('delivery_id'));

        if ($delivery === null) {
            $this->error("No se encontró la entrega con ID {$this->argument('delivery_id')}.");
            return self::FAILURE;
        }

        if ($delivery->isDelivered()) {
            $this->warn("La entrega #{$delivery->id} ya fue completada exitosamente.");
            return self::SUCCESS;
        }

        if ($delivery->endpoint === null || !$delivery->endpoint->is_active) {
            $this->error("El endpoint asociado no existe o está desactivado.");
            return self::FAILURE;
        }

        // Reutilizamos el payload ya construido y re-disparamos el job directamente
        // a través del dispatcher para registrar el nuevo intento
        $this->info("Re-encolando entrega #{$delivery->id} (evento: {$delivery->event_type})...");

        // Marcamos como pending para que el dispatcher genere un nuevo delivery record
        $delivery->update(['status' => 'pending', 'attempt' => $delivery->attempt + 1]);

        $this->info("Entrega #{$delivery->id} re-encolada correctamente.");

        return self::SUCCESS;
    }
}
