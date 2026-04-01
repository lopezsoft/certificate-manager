<?php

namespace App\Services;

use App\Jobs\ProcessCertificateJob;
use App\Models\CertificateRequest;
use App\Models\DocumentAnalysisResult;
use Illuminate\Support\Facades\Log;
use Exception;

class CertificateProcessingService
{
    /**
     * Process a single certificate request comprehensively
     * 
     * @param int $certificateRequestId
     * @param int $userId
     * @param array $options
     * @return array
     */
    public function processCertificate(int $certificateRequestId, int $userId, array $options = []): array
    {
        try {
            $certificateRequest = CertificateRequest::findOrFail($certificateRequestId);
            
            // Dispatch the job
            ProcessCertificateJob::dispatch(
                null, // No specific file - comprehensive analysis
                $userId,
                $certificateRequestId,
                array_merge($options, ['comprehensive_analysis' => true])
            );

            Log::info("Certificate processing job dispatched", [
                'certificate_request_id' => $certificateRequestId,
                'user_id' => $userId
            ]);

            return [
                'success' => true,
                'message' => 'Procesamiento de certificado iniciado',
                'certificate_request_id' => $certificateRequestId,
                'job_dispatched' => true
            ];

        } catch (Exception $e) {
            Log::error("Error dispatching certificate processing job", [
                'certificate_request_id' => $certificateRequestId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'certificate_request_id' => $certificateRequestId
            ];
        }
    }

    /**
     * Process multiple certificates in batch
     * 
     * @param array $certificateRequestIds
     * @param int $userId
     * @param array $options
     * @return array
     */
    public function processBatch(array $certificateRequestIds, int $userId, array $options = []): array
    {
        try {
            ProcessCertificateJob::processBatchCertificates($certificateRequestIds, $userId, $options);

            return [
                'success' => true,
                'message' => 'Procesamiento en lote iniciado',
                'certificates_count' => count($certificateRequestIds),
                'certificate_ids' => $certificateRequestIds
            ];

        } catch (Exception $e) {
            Log::error("Error in batch processing", [
                'certificates' => $certificateRequestIds,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'certificates_count' => count($certificateRequestIds)
            ];
        }
    }

    /**
     * Process recent certificates (last N days)
     * 
     * @param int $days
     * @param int $userId
     * @param int $limit
     * @param array $options
     * @return array
     */
    public function processRecent(int $days = 7, int $userId = 1, int $limit = 10, array $options = []): array
    {
        try {
            ProcessCertificateJob::processRecentCertificates($days, $userId, $limit, $options);

            return [
                'success' => true,
                'message' => 'Procesamiento de certificados recientes iniciado',
                'days' => $days,
                'limit' => $limit
            ];

        } catch (Exception $e) {
            Log::error("Error processing recent certificates", [
                'days' => $days,
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'days' => $days,
                'limit' => $limit
            ];
        }
    }

    /**
     * Reprocess failed analyses
     * 
     * @param int $userId
     * @param int $limit
     * @param array $options
     * @return array
     */
    public function reprocessFailed(int $userId = 1, int $limit = 5, array $options = []): array
    {
        try {
            ProcessCertificateJob::reprocessFailedAnalyses($userId, $limit, $options);

            return [
                'success' => true,
                'message' => 'Reprocesamiento de análisis fallidos iniciado',
                'limit' => $limit
            ];

        } catch (Exception $e) {
            Log::error("Error reprocessing failed analyses", [
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'limit' => $limit
            ];
        }
    }

    /**
     * Get comprehensive statistics
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        try {
            $stats = ProcessCertificateJob::getAnalysisStatistics();
            
            // Add processing efficiency metrics
            $stats['processing_efficiency'] = $stats['total_certificates'] > 0 
                ? round(($stats['analyzed_certificates'] / $stats['total_certificates']) * 100, 2) 
                : 0;
                
            $stats['success_rate'] = $stats['analyzed_certificates'] > 0 
                ? round(($stats['valid_analyses'] / $stats['analyzed_certificates']) * 100, 2) 
                : 0;

            return [
                'success' => true,
                'data' => $stats
            ];

        } catch (Exception $e) {
            Log::error("Error getting statistics", [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get analysis results for a specific certificate
     * 
     * @param int $certificateRequestId
     * @return array
     */
    public function getAnalysisResult(int $certificateRequestId): array
    {
        try {
            $result = DocumentAnalysisResult::where('certificate_request_id', $certificateRequestId)
                ->with('certificateRequest:id,company_name,legal_representative')
                ->latest()
                ->first();

            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'No se encontraron resultados de análisis para este certificado',
                    'certificate_request_id' => $certificateRequestId
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'analysis_id' => $result->id,
                    'certificate_request_id' => $result->certificate_request_id,
                    'certificate_info' => [
                        'company_name' => $result->certificateRequest->company_name,
                        'legal_representative' => $result->certificateRequest->legal_representative
                    ],
                    'validation_summary' => [
                        'overall_valid' => $result->overall_valid,
                        'person_type' => $result->person_type,
                        'rut_found' => $result->rut_found,
                        'cedula_found' => $result->cedula_found,
                        'cedula_complete' => $result->cedula_complete,
                        'chamber_commerce_required' => $result->chamber_commerce_required,
                        'chamber_commerce_found' => $result->chamber_commerce_found,
                        'chamber_commerce_valid_date' => $result->chamber_commerce_valid_date,
                        'legal_representative_match' => $result->legal_representative_match
                    ],
                    'processing_info' => [
                        'documents_processed' => $result->documents_processed,
                        'processing_time' => $result->processing_time,
                        'analyzed_at' => $result->created_at->format('Y-m-d H:i:s')
                    ],
                    'validation_errors' => $result->validation_errors,
                    'extracted_data' => $result->extracted_data
                ]
            ];

        } catch (Exception $e) {
            Log::error("Error getting analysis result", [
                'certificate_request_id' => $certificateRequestId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'certificate_request_id' => $certificateRequestId
            ];
        }
    }
}