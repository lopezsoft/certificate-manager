<?php

namespace App\Webhooks\Events;

use App\Payments\Models\PaymentTransaction;
use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Enums\WebhookEventType;

class PaymentApprovedWebhookEvent implements WebhookEventContract
{
    public function __construct(
        private readonly PaymentTransaction $transaction,
    ) {}

    public function eventType(): string
    {
        return WebhookEventType::PAYMENT_APPROVED;
    }

    public function companyId(): int
    {
        return $this->transaction->order->company_id;
    }

    public function resourceData(): array
    {
        return [
            'transaction_id'          => $this->transaction->id,
            'provider_transaction_id' => $this->transaction->provider_transaction_id,
            'provider_reference'      => $this->transaction->provider_reference,
            'payment_provider'        => $this->transaction->payment_provider,
            'order_id'                => $this->transaction->certificate_order_id,
            'amount'                  => $this->transaction->amount,
            'currency'                => $this->transaction->currency,
            'payment_method_type'     => $this->transaction->payment_method_type,
            'paid_at'                 => $this->transaction->paid_at?->toIso8601String(),
        ];
    }
}

