<?php

namespace App\Validators;

use App\Models\Events\DocumentReception;
use App\Models\Events\DocumentReceptionPerson;
use App\Models\Settings\Resolution;
use Exception;

class EventMasterValidator
{
    /**
     * @throws Exception
     */
    public function receptionPerson($company): object
    {
        $receptionPerson = DocumentReceptionPerson::query()
            ->where('company_id', $company->id)
            ->first();
        if (!$receptionPerson) {
            throw new Exception('No tiene una persona de recepción asignada', 400);
        }
        return $receptionPerson;
    }
    /**
     * @throws Exception
     */
    public function resolution($company): object
    {
        $resolution = Resolution::query()
            ->where('company_id', $company->id)
            ->where('type_document_id', 12)
            ->where('active', 1)
            ->first();
        if (!$resolution) {
            throw new Exception('No tiene una numeración activa para los eventos de recepción', 400);
        }
        return $resolution;
    }

    /**
     * @throws Exception
     */
    public function documentReception($trackId) : object
    {
        $documentReception  = DocumentReception::query()->where('cufe_cude', $trackId)->first();
        if (!$documentReception) {
            throw new Exception('El documento de recepción no existe', 400);
        }
        return $documentReception;
    }
}
