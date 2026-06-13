<?php

declare(strict_types=1);

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Models\EntityDocumentType;
use App\Models\IdentityDocument;
use App\Models\TypeOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ReferencedTablesService
{
    /** TTL de caché para datos maestros (raramente cambian) */
    private const CACHE_TTL_HOURS = 24;

    public function getTypeOrganization(): JsonResponse
    {
        $data = Cache::remember('master.type_organization', now()->addHours(self::CACHE_TTL_HOURS), fn () =>
            TypeOrganization::all()
        );

        return HttpResponseMessages::getResponse([
            'dataRecords' => ['data' => $data],
        ]);
    }

    public function getIdentityDocuments(): JsonResponse
    {
        $data = Cache::remember('master.identity_documents', now()->addHours(self::CACHE_TTL_HOURS), fn () =>
            IdentityDocument::all()
        );

        return HttpResponseMessages::getResponse([
            'dataRecords' => ['data' => $data],
        ]);
    }

    public function getEntityDocumentTypes(): JsonResponse
    {
        $data = Cache::remember('master.entity_document_types', now()->addHours(self::CACHE_TTL_HOURS), fn () =>
            EntityDocumentType::where('active', true)->get()
        );

        return HttpResponseMessages::getResponse([
            'dataRecords' => ['data' => $data],
        ]);
    }
}
