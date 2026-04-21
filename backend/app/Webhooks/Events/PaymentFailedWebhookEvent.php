<?php

namespace App\Webhooks\Events;

use App\Payments\Models\PaymentTransaction;
use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Enums\WebhookEventType;

class PaymentFailedWebhookEvent implements WebhookEventContract
{
    public function __construct(
        private readonly PaymentTransaction $transaction,
        private readonly string             $reason,
    ) {}

    public function eventType(): string
    {
        return WebhookEventType::PAYMENT_FAILED;
    }

    public function companyId(): int
    {
        return $this->transaction->order->company_id;
    }

    public function resourceData(): array
    {
        return [
            'transaction_id'      => $this->transaction->id,
            'wompi_transaction_id' => $this->transaction->wompi_transaction_id,
            'wompi_reference'     => $this->transaction->wompi_reference,
            'order_id'            => $this->transaction->certificate_order_id,
            'amount_in_cents'     => $this->transaction->amount_in_cents,
            'currency'            => $this->transaction->currency,
            'payment_method_type' => $this->transaction->payment_method_type,
            'reason'              => $this->reason,
        ];
    }
}

