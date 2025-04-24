<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class S3UploadFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public $localPath,
        public $s3Path,
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Storage::cloud()->put($this->s3Path, Storage::get($this->localPath));
        $isProduction = env('APP_ENV') == 'production';
        if ($isProduction) {
            Storage::delete($this->localPath);
        }
    }
}
