<?php

namespace App\Quotas\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * QuotaController — Solo Admin LOPEZSOFT (Sprint 5)
 * @todo Sprint 5: implementar con QuotaService + middleware admin
 */
class QuotaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['message' => 'QuotaController@index — Sprint 5'], 501);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'QuotaController@store — Sprint 5'], 501);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['message' => 'QuotaController@show — Sprint 5'], 501);
    }

    public function byCompany(int $id): JsonResponse
    {
        return response()->json(['message' => 'QuotaController@byCompany — Sprint 5'], 501);
    }
}

