<?php

namespace App\Andes\Events;

use App\Andes\Models\AndesCertificateRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AndesCertificateEmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly AndesCertificateRequest $andesCertificateRequest,
    ) {}
}

