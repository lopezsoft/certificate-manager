<?php

namespace App\Listeners;

use App\Events\CertificateProcessedWithAI;
use App\Models\CertificateRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleCertificateAIProcessing implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CertificateProcessedWithAI $event): void
    {
        try {
            Log::info('Processing AI results for certificate', [
                'certificate_request_id' => $event->certificateRequestId,
                'file_id' => $event->fileId,
                'processing_time' => $event->processingTime,
                'user_id' => $event->userId
            ]);

            // Auto-populate certificate data if AI analysis was successful
            if (isset($event->aiResults['ai_analysis']) && !empty($event->aiResults['ai_analysis'])) {
                $this->autoPopulateCertificateData($event->certificateRequestId, $event->aiResults['ai_analysis']);
            }

            // Store AI processing metrics for analytics
            $this->storeProcessingMetrics($event);

            // Send notification if needed
            if ($event->processingTime > 60) { // If processing took more than 1 minute
                Log::warning('AI processing took longer than expected', [
                    'certificate_request_id' => $event->certificateRequestId,
                    'processing_time' => $event->processingTime
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error handling AI processing event', [
                'error' => $e->getMessage(),
                'certificate_request_id' => $event->certificateRequestId,
                'file_id' => $event->fileId
            ]);
        }
    }

    /**
     * Auto-populate certificate data based on AI analysis
     *
     * @param int $certificateRequestId
     * @param array $aiAnalysis
     * @return void
     */
    private function autoPopulateCertificateData(int $certificateRequestId, array $aiAnalysis): void
    {
        try {
            $certificateRequest = CertificateRequest::find($certificateRequestId);
            
            if (!$certificateRequest) {
                Log::warning('Certificate request not found for auto-population', [
                    'certificate_request_id' => $certificateRequestId
                ]);
                return;
            }

            $updateData = [];

            // Map AI analysis fields to database fields
            if (!empty($aiAnalysis['nombre_completo']) && empty($certificateRequest->legal_representative)) {
                $updateData['legal_representative'] = strtoupper($aiAnalysis['nombre_completo']);
            }

            if (!empty($aiAnalysis['documento_identidad']) && empty($certificateRequest->document_number)) {
                $updateData['document_number'] = $aiAnalysis['documento_identidad'];
            }

            if (!empty($aiAnalysis['institucion']) && empty($certificateRequest->info)) {
                $updateData['info'] = $aiAnalysis['institucion'];
            }

            // Only update if we have data to update and fields are currently empty
            if (!empty($updateData)) {
                $certificateRequest->update($updateData);
                
                Log::info('Auto-populated certificate data from AI analysis', [
                    'certificate_request_id' => $certificateRequestId,
                    'updated_fields' => array_keys($updateData)
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error auto-populating certificate data', [
                'error' => $e->getMessage(),
                'certificate_request_id' => $certificateRequestId
            ]);
        }
    }

    /**
     * Store processing metrics for analytics
     *
     * @param CertificateProcessedWithAI $event
     * @return void
     */
    private function storeProcessingMetrics(CertificateProcessedWithAI $event): void
    {
        try {
            // Here you could store metrics in a separate table for analytics
            // For now, we'll just log the metrics
            
            $metrics = [
                'certificate_request_id' => $event->certificateRequestId,
                'file_id' => $event->fileId,
                'processing_time' => $event->processingTime,
                'user_id' => $event->userId,
                'ocr_confidence' => $event->aiResults['ocr_results']['confidence'] ?? null,
                'classification_type' => $event->aiResults['classification']['document_type'] ?? null,
                'classification_confidence' => $event->aiResults['classification']['confidence'] ?? null,
                'ai_analysis_success' => !empty($event->aiResults['ai_analysis']),
                'processed_at' => now()->toISOString()
            ];

            Log::info('AI Processing Metrics', $metrics);

            // [Sprint 4] Persistir en tabla ai_processing_metrics cuando se implemente el dashboard.
            // DB::table('ai_processing_metrics')->insert($metrics);

        } catch (\Exception $e) {
            Log::error('Error storing processing metrics', [
                'error' => $e->getMessage(),
                'certificate_request_id' => $event->certificateRequestId
            ]);
        }
    }
}
