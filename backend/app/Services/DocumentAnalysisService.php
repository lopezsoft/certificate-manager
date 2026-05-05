<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AiAnalysisServiceContract;
use App\Contracts\OcrServiceContract;
use App\Models\DocumentAnalysisResult;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta el pipeline completo: OCR → IA → Persistencia.
 *
 * No conoce los proveedores concretos. Opera exclusivamente
 * a través de OcrServiceContract y AiAnalysisServiceContract.
 */
class DocumentAnalysisService
{
    public function __construct(
        private readonly OcrServiceContract         $ocrService,
        private readonly AiAnalysisServiceContract  $aiService,
    ) {}

    /**
     * Ejecuta el pipeline completo para un documento.
     */
    public function analyzeDocument(
        int    $certificateRequestId,
        string $filePath,
        ?int   $fileManagerId = null,
        string $analysisType = 'general',
        ?int   $userId = null,
    ): DocumentAnalysisResult {
        $startTime = microtime(true);

        $result = DocumentAnalysisResult::create([
            'certificate_request_id' => $certificateRequestId,
            'file_manager_id'        => $fileManagerId,
            'provider'               => $this->aiService->providerName(),
            'analysis_type'          => $analysisType,
            'ocr_provider'           => $this->ocrService->providerName(),
            'status'                 => 'PROCESSING',
            'processed_by'           => $userId,
        ]);

        try {
            $ocrResult = $this->ocrService->extractText($filePath);

            if (! $ocrResult['success']) {
                return $this->markFailed($result, $ocrResult['message'] ?? 'Error OCR', $startTime);
            }

            $ocrText       = $ocrResult['data']['text'] ?? '';
            $ocrConfidence = $ocrResult['data']['confidence'] ?? 0;

            $result->update([
                'ocr_text'       => $ocrText,
                'ocr_confidence' => $ocrConfidence,
            ]);

            $aiResult = $this->aiService->analyzeCertificateText($ocrText, [
                'analysis_type' => $analysisType,
            ]);

            if (! $aiResult['success']) {
                return $this->markFailed($result, $aiResult['error'] ?? 'Error IA', $startTime);
            }

            $aiData        = $aiResult['data'] ?? [];
            $aiResponseRaw = $aiData['text'] ?? '';
            $parsedData    = $this->parseAiResponse($aiResponseRaw);

            $result->update([
                'ai_response'       => $aiData,
                'ai_confidence'     => $aiData['confidence'] ?? 0,
                'completeness_score' => $parsedData['completeness_score'] ?? null,
                'extracted_data'    => $parsedData['extracted_data'] ?? null,
                'validation_result' => $parsedData['validation'] ?? null,
                'processing_time'   => round(microtime(true) - $startTime, 3),
                'status'            => 'COMPLETED',
            ]);

            Log::info('[ANALYSIS] Pipeline completado.', [
                'id'       => $result->id,
                'provider' => $this->aiService->providerName(),
                'ocr'      => $this->ocrService->providerName(),
                'time'     => $result->processing_time,
            ]);

            return $result->fresh();

        } catch (\Throwable $e) {
            Log::error('[ANALYSIS] Excepción en pipeline.', ['error' => $e->getMessage()]);
            return $this->markFailed($result, $e->getMessage(), $startTime);
        }
    }

    public function getResults(
        ?int    $companyId = null,
        ?string $analysisType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int     $perPage = 15,
    ) {
        $query = DocumentAnalysisResult::query()
            ->with('certificateRequest:id,company_id,company_name,dni')
            ->orderByDesc('id');

        if ($companyId !== null) {
            $query->whereHas('certificateRequest', fn ($q) => $q->where('company_id', $companyId));
        }

        if ($analysisType !== null) {
            $query->where('analysis_type', $analysisType);
        }

        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query->paginate($perPage);
    }

    public function getStats(?int $companyId = null): array
    {
        $query = DocumentAnalysisResult::query();

        if ($companyId !== null) {
            $query->whereHas('certificateRequest', fn ($q) => $q->where('company_id', $companyId));
        }

        return [
            'total'                  => $query->count(),
            'completed'              => (clone $query)->where('status', 'COMPLETED')->count(),
            'failed'                 => (clone $query)->where('status', 'FAILED')->count(),
            'avg_confidence'         => round((float) (clone $query)->where('status', 'COMPLETED')->avg('ai_confidence'), 2),
            'avg_processing_time'    => round((float) (clone $query)->where('status', 'COMPLETED')->avg('processing_time'), 3),
            'avg_completeness'       => round((float) (clone $query)->where('status', 'COMPLETED')->avg('completeness_score'), 2),
            'by_type'                => (clone $query)->where('status', 'COMPLETED')
                                            ->selectRaw('analysis_type, COUNT(*) as count')
                                            ->groupBy('analysis_type')
                                            ->pluck('count', 'analysis_type'),
        ];
    }

    private function markFailed(DocumentAnalysisResult $result, string $message, float $startTime): DocumentAnalysisResult
    {
        $result->update([
            'status'          => 'FAILED',
            'error_message'   => $message,
            'processing_time' => round(microtime(true) - $startTime, 3),
        ]);
        return $result->fresh();
    }

    private function parseAiResponse(string $responseText): array
    {
        $decoded = json_decode($responseText, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return [
            'extracted_data'     => ['raw_text' => $responseText],
            'validation'         => ['is_valid' => true, 'warnings' => ['Respuesta no JSON']],
            'completeness_score' => 0.50,
        ];
    }
}