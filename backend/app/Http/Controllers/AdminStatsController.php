<?php

namespace App\Http\Controllers;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Services\AdminStatsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AdminStatsController — Estadísticas transversales para administradores.
 * Todas las rutas viven bajo el grupo `admin` (middleware EnsureUserIsAdmin).
 */
class AdminStatsController extends Controller
{
    public function __construct(
        private readonly AdminStatsService $stats,
    ) {}

    /**
     * @OA\Get(
     *     path="/admin/stats/payments-by-month",
     *     tags={"Estadísticas Admin"},
     *     summary="Pagos por mes de cada cliente",
     *     description="Órdenes PAID agrupadas por empresa y mes de pago para el año indicado. Incluye totales por mes y globales.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="year", in="query", required=false, description="Año (default: actual)", @OA\Schema(type="integer", example=2026)),
     *     @OA\Parameter(name="company_id", in="query", required=false, description="Filtrar por empresa", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Matriz empresa × mes",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="year", type="integer"),
     *                 @OA\Property(property="available_years", type="array", @OA\Items(type="integer")),
     *                 @OA\Property(property="months", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="companies", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="totals", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=403, description="Se requieren permisos de administrador"),
     *     @OA\Response(response=422, description="Parámetros inválidos")
     * )
     */
    public function paymentsByMonth(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year'       => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'company_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $year      = (int) ($data['year'] ?? now()->year);
            $companyId = isset($data['company_id']) ? (int) $data['company_id'] : null;

            return HttpResponseMessages::getResponse([
                'message'     => "Pagos por mes del año {$year}",
                'dataRecords' => $this->stats->paymentsByMonth($year, $companyId),
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * @OA\Get(
     *     path="/admin/stats/unused-quotas",
     *     tags={"Estadísticas Admin"},
     *     summary="Cupos no consumidos por empresa",
     *     description="Cupos POSTPAID con saldo disponible e items PREPAID pendientes, agrupados por empresa.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="company_id", in="query", required=false, description="Filtrar por empresa", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include_expired", in="query", required=false, description="Incluir cupos POSTPAID vencidos con saldo", @OA\Schema(type="boolean", example=false)),
     *     @OA\Response(response=200, description="Cupos sin consumir",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="total", type="integer", example=4),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="companies", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="totals", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=403, description="Se requieren permisos de administrador"),
     *     @OA\Response(response=422, description="Parámetros inválidos")
     * )
     */
    public function unusedQuotas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'      => ['nullable', 'integer', 'min:1'],
            'include_expired' => ['nullable', 'boolean'],
        ]);

        try {
            $companyId      = isset($data['company_id']) ? (int) $data['company_id'] : null;
            $includeExpired = $request->boolean('include_expired');

            $result = $this->stats->unusedQuotas($companyId, $includeExpired);

            return HttpResponseMessages::getResponse([
                'message'     => 'Cupos no consumidos por empresa',
                'total'       => $result['totals']['companies'],
                'dataRecords' => $result,
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
