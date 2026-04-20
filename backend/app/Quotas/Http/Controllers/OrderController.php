<?php

namespace App\Quotas\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OrderController — Sprint 4
 * @todo Sprint 4: implementar con OrderService + PaymentOrchestrator
 */
class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['message' => 'OrderController@index — Sprint 4'], 501);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'OrderController@store — Sprint 4'], 501);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['message' => 'OrderController@show — Sprint 4'], 501);
    }

    public function pay(Request $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'OrderController@pay — Sprint 4'], 501);
    }
}

