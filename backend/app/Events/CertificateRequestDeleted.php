<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CertificateRequestDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int    $certificateRequestId,
        public readonly int    $companyId,
        public readonly string $dni,
        public readonly string $companyName,
    ) {}
}
