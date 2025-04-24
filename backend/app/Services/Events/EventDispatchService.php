<?php

namespace App\Services\Events;

use App\Models\Events\EventMaster;
use App\Models\Events\TypeEvent;
use Exception;

class EventDispatchService
{
    /**
     * @throws Exception
     */
    public static function dispatch($company, $documentReception, $resolution): void
    {
        $documentId     = $documentReception->document_type_id;
        $paymentId      = $documentReception->payment_method_id;
        // Crea los eventos de recepción
        if ($documentId === 7) { // Factura electrónica
            if ($paymentId === 2) { // Pago a crédito
                // Acuse de recibo de Factura Electrónica de Venta
                self::createReceptionEvent($company, $documentReception, "030", $resolution);
                // Recibo del bien y/o prestación del servicio
                self::createReceptionEvent($company, $documentReception, "032", $resolution);
                // Aceptación expresa
                self::createReceptionEvent($company, $documentReception, "033", $resolution);
            }
        }
    }

    /**
     * @throws Exception
     */
    private static function createReceptionEvent($company, $documentReception, $eventCode, $resolution): void
    {
        $code               = $eventCode;
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
