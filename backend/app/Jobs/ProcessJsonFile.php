<?php

namespace App\Jobs;

use App\Models\JsonData;
use App\Models\ShippingHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessJsonFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected $jsonRecord
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Procesar el archivo JSON
        $jsonRecord = $this->jsonRecord;
        $jsonRecord = JsonData::query()
            ->find($jsonRecord->id);
        $shipping = ShippingHistory::query()
            ->find($jsonRecord->shipping_id);
        $jsonData = $shipping->getJsonDataAttribute();
        if (!empty($jsonData)) {
            $jsonRecord->jdata = $jsonData;
            $jsonRecord->save();
        }
    }
}
