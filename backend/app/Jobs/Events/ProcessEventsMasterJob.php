<?php

namespace App\Jobs\Events;

use App\Enums\DocumentStatusEnum;
use App\Models\Events\EventMaster;
use App\Validators\EventMasterValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessEventsMasterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected const EVENT_LIMIT = 150;

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

        $validator  = new EventMasterValidator();

        // Procesa los eventos de acuerdo a la prioridad: primero tipo 3,
        // si no existen, procesa tipo 5 y, en caso de no haber ninguno, procesa tipo 6.
        if ($this->processEventsByType(3, $validator) === 0) { // 030 - Acuse de recibo de Factura Electrónica de Venta
            if ($this->processEventsByType(5, $validator) === 0) { // 032 - Recibo del bien y/o prestación del servicio
                $this->processEventsByType(6, $validator); // 033 - Aceptación Expresa
            }
        }

        // Siempre se procesan los eventos de tipo 4.
        $this->processEventsByType(4, $validator);  // 031 - Reclamo de la Factura Electrónica de Venta
    }

    /**
     * Obtiene y procesa los eventos según el tipo.
     *
     * @param int $typeEventId
     * @param EventMasterValidator $validator
     * @return int Cantidad de eventos procesados.
     *
     * @throws \Exception
     */
    protected function processEventsByType(int $typeEventId, EventMasterValidator $validator): int
    {
        $events = $this->getEventMasterQuery()
            ->where('type_event_id', $typeEventId)
            ->with(['typeEvent', 'company', 'resolution', 'documentReception'])
            ->get();

        foreach ($events as $event) {
            $this->processEvent($event, $validator);
        }

        return $events->count();
    }

    /**
     * Procesa un evento individual.
     *
     * @param EventMaster $event
     * @param EventMasterValidator $validator
     *
     * @throws \Exception
     */
    protected function processEvent(EventMaster $event, EventMasterValidator $validator): void
    {
        $typeEvent          = $event->typeEvent;
        $company            = $event->company;
        $receptionPerson    = $validator->receptionPerson($company);
        /** Si la empresa no tiene activado el envío de eventos y el evento no es
         ** Acuse de recibo de Factura Electrónica de Venta (código '030'), no se procesa.
         */
        if ($receptionPerson->send_events === 0 && $typeEvent->code !== '030') {
            return;
        }
        $params     = (object) [
            'company'           => $company,
            'typeEvent'         => $typeEvent,
            'documentReception' => $event->documentReception,
            'resolution'        => $event->resolution,
            'eventMaster'       => $event,
            'receptionPerson'   => $receptionPerson,
            'notes'             => $event->description
        ];

        SendReceptionEventJob::dispatch($params);
    }

    protected function getEventMasterQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return EventMaster::query()
            ->whereIn('document_status', [DocumentStatusEnum::getPending(), DocumentStatusEnum::getProcessing()])
            ->where('created_at', '>=', now()->subDays(3))
            ->limit(self::EVENT_LIMIT);
    }
}
