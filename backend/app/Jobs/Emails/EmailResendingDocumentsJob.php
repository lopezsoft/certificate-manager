<?php

namespace App\Jobs\Emails;

use App\Models\Email\DocumentEmailJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmailResendingDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     * @throws \Exception
     */
    public function handle(): void
    {
        $emailJob = DocumentEmailJob::query()
            ->with(['document', 'company'])
            ->whereBetween('created_at', [now()->subMinutes(15), now()->subMinutes(0)]) // Es: 15 minutos antes de la fecha actual. En: 15 minutes before the current date.
            ->limit(100)->get();
        if ($emailJob->count() > 0) {
            foreach ($emailJob as $job) {
                EmailSendingDocumentJob::dispatch($job);
            }
        }
    }
}
