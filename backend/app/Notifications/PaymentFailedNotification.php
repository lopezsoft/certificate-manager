<?php

namespace App\Notifications;

use App\Payments\Models\PaymentTransaction;
use App\Quotas\Models\CertificateOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CertificateOrder   $order,
        private readonly PaymentTransaction $transaction,
        private readonly string             $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('❌ Pago rechazado — Referencia ' . $this->transaction->provider_reference)
            ->greeting('Hola, ' . ($notifiable->name ?? 'estimado usuario') . '.')
            ->error()
            ->line('Tu pago no pudo ser procesado.')
            ->line('**Detalle del intento:**')
            ->line('- Referencia: ' . $this->transaction->provider_reference)
            ->line('- Motivo: ' . $this->reason)
            ->line('Por favor intenta nuevamente con otro medio de pago.')
            ->action('Reintentar pago', url('/dashboard'))
            ->line('Si el problema persiste, contáctanos.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id'       => $this->order->id,
            'transaction_id' => $this->transaction->id,
            'reason'         => $this->reason,
        ];
    }
}

