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
     *     tags={"v2 - Analíticas IA"},
     *     summary="Listar resultados de análisis IA",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="analysis_type", in="query", required=false, @OA\Schema(type="string", enum={"general","rut","cedula","chamber_commerce"})),
     *     @OA\Parameter(name="date_from", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Lista paginada de resultados")
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
     *     tags={"v2 - Analíticas IA"},
     *     summary="Estadísticas resumidas de análisis IA",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Estadísticas agregadas")
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
     *     tags={"v2 - Analíticas IA"},
     *     summary="Detalle de un resultado de análisis",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detalle del análisis"),
     *     @OA\Response(response=404, description="No encontrado")
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
     *     tags={"v2 - Analíticas IA"},
     *     summary="Proveedores IA/OCR activos",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Estado de proveedores")
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
