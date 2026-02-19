<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CertificateStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int     $certificateRequestId,
        public readonly int     $companyId,
        public readonly string  $previousStatus,
        public readonly string  $newStatus,
        public readonly int     $userId,
        public readonly ?string $comment = null,
    ) {}
}
