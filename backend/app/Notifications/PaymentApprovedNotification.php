<?php

namespace App\Notifications;

use App\Payments\Models\PaymentTransaction;
use App\Quotas\Models\CertificateOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CertificateOrder    $order,
        private readonly PaymentTransaction  $transaction,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format((float) $this->transaction->amount, 0, ',', '.');

        return (new MailMessage)
            ->subject('✅ Pago aprobado — ' . $this->order->quantity . ' certificado(s) disponibles')
            ->greeting('¡Hola, ' . ($notifiable->name ?? 'estimado usuario') . '!')
            ->line('Tu pago ha sido aprobado exitosamente.')
            ->line('**Resumen de la orden:**')
            ->line('- Referencia: ' . $this->transaction->provider_reference)
            ->line('- Cantidad: ' . $this->order->quantity . ' certificado(s)')
            ->line('- Vigencia: ' . $this->order->vigencia . ' año(s)')
            ->line('- Total pagado: $' . $amount . ' COP')
            ->line('Ya puedes emitir tus certificados digitales.')
            ->action('Ver mis certificados', url('/dashboard'))
            ->line('Gracias por confiar en nosotros.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id'        => $this->order->id,
            'transaction_id'  => $this->transaction->id,
            'amount'          => $this->transaction->amount,
        ];
    }
}

