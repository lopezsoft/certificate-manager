<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Acquirer\AcquirerService;
use Illuminate\Http\Request;

class AcquirerController extends Controller
{
    public function getAcquirer(Request $request): \Illuminate\Http\JsonResponse
    {
        return AcquirerService::getAcquirer($request);
    }
}
