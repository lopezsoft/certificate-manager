<?php

declare(strict_types=1);

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Models\general\Countries;
use App\Models\Location\Cities;
use App\Models\Location\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LocationService
{
    /** TTL de caché para catálogos geográficos (raramente cambian) */
    private const CACHE_TTL_HOURS = 24;

    public function getCountries(): JsonResponse
    {
        $data = Cache::remember('location.countries', now()->addHours(self::CACHE_TTL_HOURS), fn () =>
            Countries::query()->where('active', true)->get()
        );

        return HttpResponseMessages::getResponse([
            'dataRecords' => ['data' => $data],
        ]);
    }

    public function getDepartments(): JsonResponse
    {
        $data = Cache::remember('location.departments', now()->addHours(self::CACHE_TTL_HOURS), fn () =>
            Department::all()
        );

        return HttpResponseMessages::getResponse([
            'dataRecords' => ['data' => $data],
        ]);
    }

    public function getCities(Request $request): JsonResponse
    {
        $query  = Cities::query()->with(['postalCode']);
        $search = $request->input('query');
        $code   = $request->input('code');

        if ($search) {
            $query->where('name_city', 'like', "%$search%")
                ->orWhere('city_code', 'like', "%$search%");
        }

        if ($code) {
            $query->where('city_code', $code);
        }

        return HttpResponseMessages::getResponse([
            'dataRecords' => ['data' => $query->get()],
        ]);
    }
}
