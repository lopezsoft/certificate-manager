<?php

namespace App\Payments\DTOs;

class CreateTransactionRequest
{
    public function __construct(
        public int    $amountInCents,
        public string $currency,
        public string $reference,
        public string $customerEmail,
        public string $acceptanceToken,
        public string $paymentSourceId,      // token de tarjeta / Nequi / PSE
        public string $paymentMethod,        // CARD | NEQUI | PSE | BANCOLOMBIA_TRANSFER
        public ?string $installments = null, // cuotas (solo tarjetas)
    ) {}

    public function toArray(): array
    {
        $data = [
            'amount_in_cents'   => $this->amountInCents,
            'currency'          => $this->currency,
            'customer_email'    => $this->customerEmail,
            'reference'         => $this->reference,
            'acceptance_token'  => $this->acceptanceToken,
            'payment_method'    => [
                'type'              => $this->paymentMethod,
                'installments'      => $this->installments ?? 1,
                'token'             => $this->paymentSourceId,
            ],
        ];

        return $data;
    }
}


