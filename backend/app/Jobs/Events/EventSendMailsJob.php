<?php

namespace App\Jobs\Events;

use App\Enums\DocumentStatusEnum;
use App\Models\Events\EventMaster;
use App\Services\Events\EventSendMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EventSendMailsJob implements ShouldQueue
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
        $eventMaster = EventMaster::query()
            ->where('send_mail', 0)
            ->where('document_status', DocumentStatusEnum::getAccepted())
            ->limit(100)
            ->get();
        $eventService = new EventSendMailService();
        foreach ($eventMaster as $event) {
           $eventService->send($event->id);
        }
    }
}
