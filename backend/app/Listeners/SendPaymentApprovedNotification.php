<?php

namespace App\Listeners;

use App\Payments\Events\PaymentApproved;
use App\Webhooks\Events\PaymentApprovedWebhookEvent;
use App\Webhooks\Jobs\DeliverWebhookJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PaymentApprovedNotification;

/**
 * SendPaymentApprovedNotification
 *
 * Escucha el evento PaymentApproved y:
 * 1. Envía notificación por email al usuario de la orden.
 * 2. Despacha el webhook al endpoint registrado de la empresa.
 */
class SendPaymentApprovedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(PaymentApproved $event): void
    {
        $transaction = $event->transaction;
        $transaction->loadMissing(['order.user', 'order.company']);
        $order = $transaction->order;

        if ($order?->user) {
            $order->user->notify(new PaymentApprovedNotification($order, $transaction));

            Log::info('PaymentApproved notification sent', [
                'user_id'    => $order->user_id,
                'order_id'   => $order->id,
                'company_id' => $order->company_id,
            ]);
        }

        DeliverWebhookJob::dispatch(
            new PaymentApprovedWebhookEvent($transaction)
        );
    }
}


