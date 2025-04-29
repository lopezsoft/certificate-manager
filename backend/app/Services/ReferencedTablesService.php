<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Models\IdentityDocument;
use App\Models\TypeOrganization;
use Illuminate\Http\JsonResponse;

class ReferencedTablesService
{
    public static function getTypeOrganization(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => TypeOrganization::all(),
            ]
        ]);
    }
    public static function getIdentityDocuments(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => IdentityDocument::all(),
            ]
        ]);
    }
}
