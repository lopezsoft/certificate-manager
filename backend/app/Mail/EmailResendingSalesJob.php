<?php

namespace App\Jobs\Sales;

use App\Models\Sales\SaleEmailJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmailResendingSalesJob implements ShouldQueue
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
        $emailJob = SaleEmailJob::query()
            ->with(['sale', 'company', 'user'])
            ->limit(100)->get();

        foreach ($emailJob as $job) {
            $params = (object)[
                'sale'              => $job->sale,
                'user'              => $job->user,
                'company'           => $job->company
            ];
            EmailSendingSalesJob::dispatch($params);
        }
    }
}
