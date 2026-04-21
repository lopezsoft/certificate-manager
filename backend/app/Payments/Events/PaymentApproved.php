<?php

namespace App\Payments\Events;

use App\Payments\Models\PaymentTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PaymentTransaction $transaction,
    ) {}
}

