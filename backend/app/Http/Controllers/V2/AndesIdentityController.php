<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AndesIdentityController
 *
 * Controlador para el flujo de validación de identidad con ANDES ID REST API.
 * Implementación completa en Sprint 2.
 *
 * @todo Sprint 2: implementar con AndesIdentityService
 */
class AndesIdentityController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        return response()->json(['message' => 'AndesIdentityController@start — Sprint 2'], 501);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        return response()->json(['message' => 'AndesIdentityController@verifyOtp — Sprint 2'], 501);
    }

    public function verifyQuestions(Request $request): JsonResponse
    {
        return response()->json(['message' => 'AndesIdentityController@verifyQuestions — Sprint 2'], 501);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        return response()->json(['message' => 'AndesIdentityController@resendOtp — Sprint 2'], 501);
    }

    public function bypassToQuestions(Request $request): JsonResponse
    {
        return response()->json(['message' => 'AndesIdentityController@bypassToQuestions — Sprint 2'], 501);
    }

    public function checkStatus(Request $request): JsonResponse
    {
        return response()->json(['message' => 'AndesIdentityController@checkStatus — Sprint 2'], 501);
    }
}

