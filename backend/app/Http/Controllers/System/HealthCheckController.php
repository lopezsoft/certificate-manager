<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Payments\Services\WompiPaymentService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint de observabilidad para monitorear el estado de los servicios
 * externos integrados (WOMPI, en el futuro Viafirma RA, etc.).
 *
 * Reemplaza a {@see \App\Http\Controllers\V2\HealthCheckController} que
 * quedaba bajo el namespace V2 por motivos históricos.
 *
 * Sólo accesible por administradores.
 */
class HealthCheckController extends Controller
{
    public function __construct(
        private readonly WompiPaymentService $wompi,
    ) {}

    /**
     * @OA\Get(
     *     path="/health",
     *     tags={"Sistema"},
     *     summary="Estado de salud de los servicios externos",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Todos los servicios operativos"),
     *     @OA\Response(response=503, description="Uno o más servicios con problemas")
     * )
     */
    public function __invoke(): JsonResponse
    {
        $wompiStatus = $this->checkWompi();
        $allOk = $wompiStatus['status'] !== 'error';

        return response()->json([
            'status'     => $allOk ? 'healthy' : 'degraded',
            'services'   => [
                'wompi' => $wompiStatus,
            ],
            'checked_at' => now()->toIso8601String(),
        ], $allOk ? 200 : 503);
    }

    private function checkWompi(): array
    {
        if (empty(config('wompi.public_key'))) {
            return ['status' => 'warning', 'message' => 'WOMPI_PUBLIC_KEY no configurado'];
        }

        try {
            $info = $this->wompi->getMerchantInfo();
            $name = $info['data']['name'] ?? null;
            return ['status' => 'ok', 'message' => 'WOMPI disponible' . ($name ? " ({$name})" : '')];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'WOMPI no responde'];
        }
    }
}

