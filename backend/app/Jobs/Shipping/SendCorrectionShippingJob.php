<?php

namespace App\Jobs\Shipping;

use App\Models\ShippingHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCorrectionShippingJob implements ShouldQueue
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
        $query      = ShippingHistory::query()
            ->where('is_valid', '=', 2)
            ->orderBy('invoice_date', 'desc')
            ->limit(500);
        $dataRecords = $query->get();
        foreach ($dataRecords as $dataRecord) {
            ShippingValidStatusJob::dispatch($dataRecord);
        }
    }
}
