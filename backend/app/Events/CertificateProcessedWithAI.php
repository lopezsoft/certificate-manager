<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CertificateProcessedWithAI
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $certificateRequestId;
    public $fileId;
    public $aiResults;
    public $processingTime;
    public $userId;

    /**
     * Create a new event instance.
     */
    public function __construct(int $certificateRequestId, int $fileId, array $aiResults, float $processingTime, int $userId)
    {
        $this->certificateRequestId = $certificateRequestId;
        $this->fileId = $fileId;
        $this->aiResults = $aiResults;
        $this->processingTime = $processingTime;
        $this->userId = $userId;
    }
}
