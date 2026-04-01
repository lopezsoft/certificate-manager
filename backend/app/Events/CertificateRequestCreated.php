<?php

namespace App\Events;

use App\Models\CertificateRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CertificateRequestCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CertificateRequest $certificate,
    ) {}
}
