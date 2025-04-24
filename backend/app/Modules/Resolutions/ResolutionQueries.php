<?php

namespace App\Modules\Resolutions;

use App\Models\Company;
use Exception;
use Illuminate\Http\Request;

class ResolutionQueries
{
    public static function getResolutionById(Company $company,  $type_document_id = 1, $resolution_number = null): object | null
    {
        $resolution = $company->resolutions()
            ->where('type_document_id', $type_document_id);
        if ($resolution_number && $resolution) {
            $resolution->where('resolution_number', $resolution_number);
        }
        return $resolution->first();
    }

    /**
     * @throws Exception
     */
    public static function getResolution(Request $request, $params): ?object
    {
        $company            = $params->company;
        $type_document_id   = $params->type_document_id;
        $resolution_number  = $params->resolution_number;
        $prefix             = $params->prefix;
        $resolution = $company->resolutions()
            ->where('type_document_id', $type_document_id);
        if ($resolution_number && $resolution) {
            $resolution->where('resolution_number', $resolution_number);
        }
        if ($prefix && $resolution) {
            $resolution->where('prefix', $prefix);
        }
        $resolution = $resolution->first();
        if (!$resolution) {
            throw new Exception("La resolución de facturación: {$resolution_number}, no existe. Empresa {$company->company_name}-{$company->dni}", 400);
        }
        $rule       = [
            'document_number'    => 'required|integer|between:'.optional($resolution)->range_from.','.optional($resolution)->range_up
        ];
        $message    = [
            'document_number.between'   => 'El número de documento debe estar entre :min y :max.',
        ];
        $request->validate($rule, $message);
        return $resolution;
    }

    public static function getDocumentNumber(Request $request, $resolution): string
    {
        $document_number     = $request->document_number;
        return $resolution->prefix . $document_number;
    }


}
