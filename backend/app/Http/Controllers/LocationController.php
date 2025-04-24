<?php

namespace App\Http\Controllers;

use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function getCountries(): JsonResponse
    {
        return LocationService::getCountries();
    }

    public function getDepartments(): JsonResponse
    {
       return LocationService::getDepartments();
    }

    public function getCities(Request $request): JsonResponse
    {
        return LocationService::getCities($request);
    }
}
