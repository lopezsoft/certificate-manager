<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DocumentAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de analíticas IA — Sprint 4.
 *
 * Consulta resultados de análisis por empresa, tipo y fecha.
 */
class DocumentAnalysisController extends Controller
{
    public function __construct(
        private readonly DocumentAnalysisService $analysisService,
    ) {}

    /**
     * @OA\Get(
     *     path="/v2/analytics/results",
     *     operationId="listAnalysisResults",
     *     tags={"v2 - Analíticas IA"},
     *     summary="Listar resultados de análisis IA",
     *     description="Retorna resultados paginados de análisis OCR+IA filtrados por empresa del usuario autenticado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="analysis_type", in="query", required=false, description="Filtrar por tipo de análisis", @OA\Schema(type="string", enum={"general","rut","cedula","chamber_commerce"})),
     *     @OA\Parameter(name="date_from", in="query", required=false, description="Fecha inicio (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", required=false, description="Fecha fin (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="Registros por página", @OA\Schema(type="integer", default=15, minimum=1, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="Lista paginada de resultados",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DocumentAnalysisResult")),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=72)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $results = $this->analysisService->getResults(
            companyId:    $request->user()->company_id,
            analysisType: $request->query('analysis_type'),
            dateFrom:     $request->query('date_from'),
            dateTo:       $request->query('date_to'),
            perPage:      (int) $request->query('per_page', 15),
        );

        return response()->json(['data' => $results]);
    }

    /**
     * @OA\Get(
     *     path="/v2/analytics/stats",
     *     operationId="getAnalyticsStats",
     *     tags={"v2 - Analíticas IA"},
     *     summary="Estadísticas resumidas de análisis IA",
     *     description="Retorna métricas agregadas: total, completados, fallidos, confianza promedio, tiempo promedio y distribución por tipo.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Estadísticas agregadas",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/AnalyticsStats")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->analysisService->getStats(
            companyId: $request->user()->company_id,
        );

        return response()->json(['data' => $stats]);
    }

    /**
     * @OA\Get(
     *     path="/v2/analytics/results/{id}",
     *     operationId="showAnalysisResult",
     *     tags={"v2 - Analíticas IA"},
     *     summary="Detalle de un resultado de análisis",
     *     description="Retorna el resultado completo incluyendo texto OCR, respuesta IA, datos extraídos y validación.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID del resultado de análisis", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Detalle del análisis",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/DocumentAnalysisResult")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="Resultado no encontrado o no pertenece a la empresa")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $result = \App\Models\DocumentAnalysisResult::whereHas(
            'certificateRequest',
            fn ($q) => $q->where('company_id', $request->user()->company_id)
        )->findOrFail($id);

        return response()->json(['data' => $result]);
    }

    /**
     * @OA\Get(
     *     path="/v2/analytics/providers",
     *     operationId="getActiveProviders",
     *     tags={"v2 - Analíticas IA"},
     *     summary="Proveedores IA/OCR activos",
     *     description="Retorna qué proveedores de OCR e IA están configurados y disponibles. Si no hay credenciales, muestra MOCK como proveedor activo.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Estado de proveedores",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/ProviderStatus")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function providers(): JsonResponse
    {
        $ocrService = app(\App\Contracts\OcrServiceContract::class);
        $aiService  = app(\App\Contracts\AiAnalysisServiceContract::class);

        return response()->json([
            'data' => [
                'ocr' => [
                    'provider'  => $ocrService->providerName(),
                    'available' => $ocrService->isAvailable(),
                ],
                'ai' => [
                    'provider'  => $aiService->providerName(),
                    'available' => $aiService->isAvailable(),
                ],
            ],
        ]);
    }
}
