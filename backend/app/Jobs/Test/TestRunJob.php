<?php

namespace App\Jobs\Test;

use App\Services\Test\TestProcessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TestRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public $processId,
    )
    {

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $processId  = $this->processId;
        TestProcessService::init($processId);
    }
}
