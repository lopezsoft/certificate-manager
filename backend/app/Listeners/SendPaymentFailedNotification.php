<?php

namespace App\Listeners;

use App\Payments\Events\PaymentFailed;
use App\Webhooks\Events\PaymentFailedWebhookEvent;
use App\Webhooks\Jobs\DeliverWebhookJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use App\Notifications\PaymentFailedNotification;

/**
 * SendPaymentFailedNotification
 *
 * Escucha el evento PaymentFailed y:
 * 1. Envía notificación por email al usuario de la orden.
 * 2. Despacha el webhook al endpoint registrado de la empresa.
 */
class SendPaymentFailedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(PaymentFailed $event): void
    {
        $transaction = $event->transaction;
        $transaction->loadMissing(['order.user', 'order.company']);
        $order = $transaction->order;

        if ($order?->user) {
            $order->user->notify(new PaymentFailedNotification($order, $transaction, $event->reason));

            Log::warning('PaymentFailed notification sent', [
                'user_id'    => $order->user_id,
                'order_id'   => $order->id,
                'company_id' => $order->company_id,
                'reason'     => $event->reason,
            ]);
        }

        DeliverWebhookJob::dispatch(
            new PaymentFailedWebhookEvent($transaction, $event->reason)
        );
    }
}


