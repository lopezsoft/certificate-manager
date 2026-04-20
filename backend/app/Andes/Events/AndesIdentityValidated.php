<?php

namespace App\Andes\Events;

use App\Andes\Models\AndesIdentityValidation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AndesIdentityValidated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly AndesIdentityValidation $validation,
    ) {}
}

