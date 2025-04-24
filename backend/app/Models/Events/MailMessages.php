<?php

namespace App\Models\Events;

use Exception;
use Illuminate\Support\Facades\Storage;

class MailMessages
{

    /**
     * @throws Exception
     */
    public static function getEventMessage($eventMaster): object
    {
        try {
            $company            = $eventMaster->company;
            $documentReception  = $eventMaster->documentReception;
            $typeEvent          = $eventMaster->typeEvent;
            $resolution         = $eventMaster->resolution;
            $typeDocument       = $resolution->type_document;
            $people             = $documentReception->people;
            // Cuerpo del mensaje del correo electrónico
            $image          = str_replace('/storage/', '', $company->image);
            $tradeName      = $company->trade_name ?? $company->company_name;

            return    (object)[
                'people'                => $people,
                'title'                 => $typeDocument->voucher_name,
                'zipPath'               => $eventMaster->zip_path,
                'company_name'          => $people->company_name,
                'replyTo'               => $company->email,
                'event_name'            => mb_strtoupper($typeEvent->name),
                'company'               => $company,
                'company_image'         => Storage::disk('public')->path($image),
                'email_from'            => 'no-reply@matiasinbox.com.co',
                'document_nro'          => $documentReception->folio,
                'total'                 => "$ ".number_format($documentReception->total,2,".",","),
                'subject'               => "{$company->dni};{$company->company_name};{$documentReception->folio};{$typeEvent->code};{$tradeName}",
            ];
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
