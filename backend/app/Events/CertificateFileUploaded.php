<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CertificateFileUploaded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int    $certificateRequestId,
        public readonly int    $companyId,
        public readonly int    $fileId,
        public readonly string $fileName,
        public readonly string $documentType,
    ) {}
}
