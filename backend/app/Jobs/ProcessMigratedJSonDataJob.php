<?php

namespace App\Jobs;

use App\Models\JsonData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMigratedJSonDataJob implements ShouldQueue
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
     */
    public function handle(): void
    {
        $data = JsonData::query()
            ->join('shipping_history', 'json_data.shipping_id', '=', 'shipping_history.id')
            ->where('is_migrated', 0)
            ->whereNotIn('shipping_history.type_document_id', ['13', '14'])
            ->limit(1500)
            ->get();

        foreach ($data as $jsonRecord) {
            MigratedJSonDataJob::dispatch($jsonRecord);
        }
    }
}
