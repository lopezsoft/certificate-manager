<?php

namespace App\Http\Controllers\V2;

use App\Andes\Services\AndesHealthCheckService;
use App\Http\Controllers\Controller;
use App\Payments\Services\WompiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

/**
 * HealthCheckController — Sprint 7
 *
 * Endpoint de observabilidad para monitorear el estado de los servicios externos.
 * Solo accesible por administradores.
 */
class HealthCheckController extends Controller
{
    public function __construct(
        private readonly AndesHealthCheckService $andesHealth,
        private readonly WompiPaymentService     $wompi,
    ) {}

    /**
     * @OA\Get(
     *     path="/v2/health",
     *     tags={"v2 - Sistema"},
     *     summary="Estado de salud de los servicios externos",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Todos los servicios operativos"),
     *     @OA\Response(response=503, description="Uno o más servicios con problemas")
     * )
     */
    public function __invoke(): JsonResponse
    {
        $andesStatus = $this->andesHealth->getStatus();
        $wompiStatus = $this->checkWompi();

        $allOk = $andesStatus['andes_id_api']['status'] === 'ok'
            && $wompiStatus['status'] !== 'error';

        return response()->json([
            'status'   => $allOk ? 'healthy' : 'degraded',
            'services' => [
                'andes_id_api' => $andesStatus['andes_id_api'],
                'andes_pki'    => $andesStatus['andes_pki'],
                'wompi'        => $wompiStatus,
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

