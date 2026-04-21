<?php

namespace App\Payments\DTOs;

use App\Payments\Enums\PaymentStatusEnum;

class TransactionResponse
{
    public function __construct(
        public readonly string             $id,
        public readonly string             $reference,
        public readonly PaymentStatusEnum  $status,
        public readonly int                $amountInCents,
        public readonly string             $currency,
        public readonly ?string            $paymentMethodType,
        public readonly array              $rawResponse,
    ) {}

    public static function fromWompiResponse(array $data): self
    {
        $tx = $data['data'] ?? $data;

        return new self(
            id:                $tx['id'] ?? '',
            reference:         $tx['reference'] ?? '',
            status:            PaymentStatusEnum::tryFrom($tx['status'] ?? '') ?? PaymentStatusEnum::ERROR,
            amountInCents:     (int) ($tx['amount_in_cents'] ?? 0),
            currency:          $tx['currency'] ?? 'COP',
            paymentMethodType: $tx['payment_method_type'] ?? null,
            rawResponse:       $tx,
        );
    }

    public function isApproved(): bool
    {
        return $this->status->isSuccessful();
    }
}

