<?php

namespace App\Quotas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Quotas\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PricingController — Endpoint privado de consulta de tarifas (Solo Admin).
 * Requiere autenticación y rol de administrador.
 *
 * GET /api/v1/pricing                 → todos los rangos
 * GET /api/v1/pricing?quantity=5&vigencia=1 → precio calculado
 */
class PricingController extends Controller
{
    public function __construct(
        private readonly PricingService $pricingService,
    ) {}

    /**
     * @OA\Get(
     *     path="/pricing",
     *     tags={"Precios"},
     *     summary="Consultar tarifas de certificados (Solo Admin)",
     *     description="Devuelve los rangos de precios desde la base de datos (pricing_tiers). Si se pasan quantity y vigencia, calcula el precio exacto. Requiere rol de admin.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="quantity", in="query", required=false, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="vigencia", in="query", required=false, description="Años: 1 o 2", @OA\Schema(type="integer", enum={1,2})),
     *     @OA\Response(response=200, description="Tarifas obtenidas"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=403, description="No autorizado (requiere admin)"),
     *     @OA\Response(response=422, description="Parámetros inválidos")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $quantity = $request->query('quantity');
        $vigencia = $request->query('vigencia');

        if ($quantity !== null && $vigencia !== null) {
            $validated = $request->validate([
                'quantity' => ['required', 'integer', 'min:1'],
                'vigencia' => ['required', 'integer', 'in:1,2'],
            ]);

            try {
                $price = $this->pricingService->calculatePrice(
                    (int) $validated['quantity'],
                    (int) $validated['vigencia'],
                );

                return response()->json([
                    'data' => $price,
                ]);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json([
            'data' => $this->pricingService->getActiveTiers(),
        ]);
    }
}

