<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Models\general\Countries;
use App\Models\Location\Cities;
use App\Models\Location\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationService
{
    public static function getCountries(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => Countries::query()->where('active', true)->get(),
            ]
        ]);
    }
    public static function getDepartments(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => Department::all(),
            ]
        ]);
    }
    public static function getCities(Request $request): JsonResponse
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
            "dataRecords" => [
                "data"  => $query->get(),
            ]
        ]);
    }
}
