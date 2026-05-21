<?php

namespace App\Quotas\Http\Controllers;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Http\Controllers\Controller;
use App\Quotas\Services\PricingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PricingController — Endpoint de consulta de tarifas de certificados.
 * Requiere autenticación (cualquier usuario autenticado puede consultarlo).
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
     *     summary="Consultar tarifas de certificados",
     *     description="Devuelve los rangos de precios desde la base de datos (pricing_tiers). Si se pasan quantity y vigencia, calcula el precio exacto.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="quantity", in="query", required=false, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="vigencia", in="query", required=false, description="Años: 1 o 2", @OA\Schema(type="integer", enum={1,2})),
     *     @OA\Response(response=200, description="Tarifas obtenidas"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=422, description="Parámetros inválidos")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $quantity = $request->query('quantity');
            $vigencia = $request->query('vigencia');

            if ($quantity !== null && $vigencia !== null) {
                $validated = $request->validate([
                    'quantity' => ['required', 'integer', 'min:1'],
                    'vigencia' => ['required', 'integer', 'in:1,2'],
                ]);

                $price = $this->pricingService->calculatePrice(
                    (int) $validated['quantity'],
                    (int) $validated['vigencia'],
                );

                return HttpResponseMessages::getResponse([
                    'message'     => 'Precio calculado exitosamente',
                    'dataRecords' => $price,
                ]);
            }

            return HttpResponseMessages::getResponse([
                'message'     => 'Tarifas obtenidas exitosamente',
                'dataRecords' => $this->pricingService->getActiveTiers(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return HttpResponseMessages::getResponse422(['message' => $e->getMessage()]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
