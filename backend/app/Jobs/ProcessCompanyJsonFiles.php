<?php

namespace App\Jobs;

use App\Queries\CallExecute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessCompanyJsonFiles implements ShouldQueue
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
        try {
            CallExecute::execute("sp_inset_json_data");

            $jsonRecords = DB::table('json_data')
                ->select('id', 'shipping_id')
                ->whereNull('jdata')
                ->limit(1000)
                ->get();

            foreach ($jsonRecords as $jsonRecord) {
                ProcessJsonFile::dispatch($jsonRecord);
            }
        }catch (\Exception $e) {
            // Log error
            Log::log('ProcessCompanyJsonFiles Error', $e->getMessage());
        }
    }
}
