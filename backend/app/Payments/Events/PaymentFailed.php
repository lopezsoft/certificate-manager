<?php

namespace App\Payments\Events;

use App\Payments\Models\PaymentTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PaymentTransaction $transaction,
        public readonly string $reason,
    ) {}
}

