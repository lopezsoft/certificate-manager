<?php

namespace App\Jobs\Events;

use App\Models\Events\EventMaster;
use App\Models\Events\TypeEvent;
use App\Services\Events\EventMasterService;
use App\Validators\EventMasterValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateReceptionEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public $company,
        public $documentReception,
        public $eventCode
    )
    {
        //
    }

    /**
     * Execute the job.
     * @throws \Exception
     */
    public function handle(): void
    {
        $validator          = new EventMasterValidator();
        $company            = $this->company;
        $resolution         = $validator->resolution($company);
        $documentReception  = $this->documentReception;
        $code               = $this->eventCode;
        $typeEvent          = TypeEvent::query()->where('code', $code)->first();

        $documentNumber     = (new EventMasterService())->nextEventNumber($company, $resolution);

        EventMaster::create([
            'company_id'            => $company->id,
            'resolution_id'         => $resolution->id,
            'document_reception_id' => $documentReception->id,
            'type_event_id'         => $typeEvent->id,
            'event_number'          => $documentNumber,
            'date_event'            => now(),
            'description'           => $typeEvent->name
        ]);
    }
}
