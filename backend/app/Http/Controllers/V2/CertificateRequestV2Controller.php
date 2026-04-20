<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CertificateRequestV2Controller
 *
 * Controlador para solicitudes de certificado vía ANDES SCD (API v2).
 * Implementación completa en Sprint 3.
 *
 * @todo Sprint 3: implementar store() y show() completos con AndesPkiService
 */
class CertificateRequestV2Controller extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'CertificateRequestV2Controller@store — Implementación pendiente (Sprint 3)',
        ], 501);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'message' => 'CertificateRequestV2Controller@show — Implementación pendiente (Sprint 3)',
        ], 501);
    }
}

