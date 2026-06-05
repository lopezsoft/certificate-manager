<?php

namespace App\Jobs;

use App\Events\CertificateProcessedWithAI;
use App\Models\CertificateRequest;
use App\Models\DocumentAnalysisResult;
use App\Models\FileManager;
use App\Services\DocumentAnalysisService;
use App\Services\OcrService;
use App\Services\AiContentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProcessCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3;

    private $filePath;
    private $userId;
    private $requestId;
    private $options;

    /**
     * Create a new job instance.
     *
     * @param string|null $filePath
     * @param int $userId
     * @param int|null $requestId
     * @param array $options
     */
    public function __construct(?string $filePath, int $userId, ?int $requestId = null, array $options = [])
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
        $this->requestId = $requestId;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        
        try {
            // Check if this is a comprehensive analysis request or no file path provided
            if ((isset($this->options['comprehensive_analysis']) && $this->options['comprehensive_analysis']) || $this->filePath === null) {
                $this->handleComprehensiveAnalysis();
                return;
            }

            Log::info("Starting certificate processing job", [
                'file_path' => $this->filePath,
                'user_id' => $this->userId,
                'request_id' => $this->requestId
            ]);

            $ocrService = app(OcrService::class);
            $aiService = app(AiContentService::class);

            // Check if file exists
            if (!Storage::exists($this->filePath)) {
                throw new Exception("File not found: {$this->filePath}");
            }

            $fullPath = Storage::path($this->filePath);

            // Extract text using OCR
            $ocrResult = $ocrService->extractDocumentData($fullPath);
            
            if (!$ocrResult['success']) {
                throw new Exception("OCR failed: " . $ocrResult['message']);
            }

            // Analyze with AI
            $aiAnalysis = $aiService->analyzeCertificateText($ocrResult['data']['full_text']);
            $classification = $aiService->classifyDocument($ocrResult['data']['full_text']);

            // Calculate processing time
            $processingTime = microtime(true) - $startTime;

            // Prepare results
            $results = [
                'ocr_results' => $ocrResult['data'],
                'ai_analysis' => $aiAnalysis['success'] ? $aiAnalysis['data'] : null,
                'classification' => $classification['success'] ? $classification['data'] : null,
                'processed_at' => now()->toISOString(),
                'processing_time' => $processingTime
            ];

            // Store results (customize based on your database structure)
            $this->storeResults($results);

            // Fire event for handling AI processing completion
            if ($this->requestId && isset($this->options['file_id'])) {
                CertificateProcessedWithAI::dispatch(
                    $this->requestId,
                    $this->options['file_id'],
                    $results,
                    $processingTime,
                    $this->userId
                );
            }

            // Send notification if email generation is requested
            if ($this->options['generate_email'] ?? false) {
                $this->generateAndSendEmail($results);
            }

            // Clean up temporary file if it's a temp file
            if (strpos($this->filePath, 'temp/') === 0) {
                Storage::delete($this->filePath);
            }

            Log::info("Certificate processing completed successfully", [
                'file_path' => $this->filePath,
                'user_id' => $this->userId,
                'request_id' => $this->requestId,
                'processing_time' => $processingTime
            ]);

        } catch (Exception $e) {
            Log::error("Certificate processing failed", [
                'error' => $e->getMessage(),
                'file_path' => $this->filePath,
                'user_id' => $this->userId,
                'request_id' => $this->requestId
            ]);

            throw $e; // Re-throw to trigger job failure handling
        }
    }

    /**
     * Store processing results
     *
     * @param array $results
     */
    private function storeResults(array $results): void
    {
        // Here you can implement logic to store results in your database
        Log::info("Results stored successfully", [
            'user_id' => $this->userId,
            'request_id' => $this->requestId
        ]);
    }

    /**
     * Generate and send email notification
     *
     * @param array $results
     */
    private function generateAndSendEmail(array $results): void
    {
        try {
            if (!$results['ai_analysis']) {
                Log::warning("Skipping email generation - no AI analysis available");
                return;
            }

            $aiService = app(AiContentService::class);
            
            // Get user name
            $userName = $this->options['recipient_name'] ?? 'Estimado usuario';
            
            $emailResult = $aiService->generateEmailContent(
                $results['ai_analysis'],
                $userName,
                $this->options['email_type'] ?? 'notification'
            );

            if ($emailResult['success']) {
                Log::info("Email content generated successfully", [
                    'user_id' => $this->userId,
                    'subject' => $emailResult['data']['subject']
                ]);
            }

        } catch (Exception $e) {
            Log::error("Email generation failed", [
                'error' => $e->getMessage(),
                'user_id' => $this->userId
            ]);
        }
    }

    /**
     * Handle job failure
     *
     * @param Exception $exception
     */
    public function failed(Exception $exception): void
    {
        Log::error("ProcessCertificateJob failed permanently", [
            'error' => $exception->getMessage(),
            'file_path' => $this->filePath,
            'user_id' => $this->userId,
            'request_id' => $this->requestId
        ]);

        // Clean up temporary file if it exists
        if (strpos($this->filePath, 'temp/') === 0 && Storage::exists($this->filePath)) {
            Storage::delete($this->filePath);
        }
    }

    /**
     * Handle comprehensive document analysis
     *
     * @return void
     */
    private function handleComprehensiveAnalysis(): void
    {
        try {
            Log::info("Starting comprehensive document analysis", [
                'certificate_request_id' => $this->requestId,
                'user_id' => $this->userId
            ]);

            if ($this->requestId) {
                static::processAllDocuments($this->requestId, $this->userId, $this->options);
            } else {
                throw new Exception("Certificate request ID is required for comprehensive analysis");
            }

        } catch (Exception $e) {
            Log::error("Error in comprehensive analysis handler", [
                'error' => $e->getMessage(),
                'certificate_request_id' => $this->requestId,
                'user_id' => $this->userId
            ]);
            throw $e;
        }
    }

    /**
     * Process all documents for a certificate request
     * This method is used when we want to analyze all documents together
     * 
     * @param int $certificateRequestId
     * @param int $userId
     * @param array $options
     * @return void
     */
    public static function processAllDocuments(int $certificateRequestId, int $userId, array $options = []): void
    {
        try {
            Log::info("Starting comprehensive document analysis for certificate request", [
                'certificate_request_id' => $certificateRequestId,
                'user_id' => $userId
            ]);

            // Get all files for this certificate request
            $files = FileManager::where('certificate_request_id', $certificateRequestId)
                ->where('document_type', 'ATTACHED')
                ->whereIn('extension_file', ['jpg', 'jpeg', 'png', 'pdf'])
                ->get();

            if ($files->isEmpty()) {
                Log::warning("No files found for comprehensive analysis", [
                    'certificate_request_id' => $certificateRequestId
                ]);
                return;
            }

            // Get file paths
            $documentPaths = [];
            $disk = Storage::disk('attachment');
            
            foreach ($files as $file) {
                if ($disk->exists($file->file_path)) {
                    $documentPaths[] = $disk->path($file->file_path);
                }
            }

            if (empty($documentPaths)) {
                Log::warning("No valid file paths found for analysis", [
                    'certificate_request_id' => $certificateRequestId
                ]);
                return;
            }

            // Perform comprehensive document analysis
            $documentAnalysisService = app(DocumentAnalysisService::class);
            $analysisResults = $documentAnalysisService->analyzeDocumentSet($documentPaths, $certificateRequestId);

            // Update certificate request with analyzed data
            static::updateCertificateRequestFromAnalysis($certificateRequestId, $analysisResults);

            // Store comprehensive analysis results
            static::storeComprehensiveResults($certificateRequestId, $analysisResults);

            Log::info("Comprehensive document analysis completed", [
                'certificate_request_id' => $certificateRequestId,
                'overall_valid' => $analysisResults['validation_summary']['overall_valid'] ?? false
            ]);

        } catch (Exception $e) {
            Log::error("Error in comprehensive document analysis", [
                'error' => $e->getMessage(),
                'certificate_request_id' => $certificateRequestId,
                'user_id' => $userId
            ]);
        }
    }

    /**
     * Update certificate request with analyzed data
     *
     * @param int $certificateRequestId
     * @param array $analysisResults
     * @return void
     */
    private static function updateCertificateRequestFromAnalysis(int $certificateRequestId, array $analysisResults): void
    {
        try {
            $certificateRequest = CertificateRequest::find($certificateRequestId);
            
            if (!$certificateRequest) {
                Log::warning('Certificate request not found for update', [
                    'certificate_request_id' => $certificateRequestId
                ]);
                return;
            }

            $updateData = [];

            // Update from RUT data
            if (isset($analysisResults['extracted_data']['rut'])) {
                $rutData = $analysisResults['extracted_data']['rut'];
                
                if (!empty($rutData['razon_social']) && empty($certificateRequest->company_name)) {
                    $updateData['company_name'] = strtoupper($rutData['razon_social']);
                }
                
                if (!empty($rutData['nit']) && empty($certificateRequest->dni)) {
                    $updateData['dni'] = preg_replace('/\D/', '', $rutData['nit']);
                }
                
                if (!empty($rutData['direccion']) && empty($certificateRequest->address)) {
                    $updateData['address'] = $rutData['direccion'];
                }
                
                if (!empty($rutData['telefono']) && empty($certificateRequest->phone)) {
                    $updateData['phone'] = $rutData['telefono'];
                }
            }

            // Update from Cédula data
            if (isset($analysisResults['extracted_data']['cedula'])) {
                $cedulaData = $analysisResults['extracted_data']['cedula'];
                
                if (!empty($cedulaData['nombres']) && !empty($cedulaData['apellidos']) && empty($certificateRequest->legal_representative)) {
                    $fullName = trim($cedulaData['nombres'] . ' ' . $cedulaData['apellidos']);
                    $updateData['legal_representative'] = strtoupper($fullName);
                }
                
                if (!empty($cedulaData['numero_cedula']) && empty($certificateRequest->document_number)) {
                    $updateData['document_number'] = preg_replace('/\D/', '', $cedulaData['numero_cedula']);
                }
            }

            // Update from Chamber of Commerce data (if applicable)
            if (isset($analysisResults['extracted_data']['chamber_commerce'])) {
                $chamberData = $analysisResults['extracted_data']['chamber_commerce'];
                
                if (!empty($chamberData['razon_social']) && empty($certificateRequest->company_name)) {
                    $updateData['company_name'] = strtoupper($chamberData['razon_social']);
                }
                
                if (!empty($chamberData['direccion']) && empty($certificateRequest->address)) {
                    $updateData['address'] = $chamberData['direccion'];
                }
            }

            // Add validation summary to info field
            $validationInfo = [
                'ai_validation' => $analysisResults['validation_summary'],
                'processed_at' => now()->toISOString()
            ];
            
            if (empty($certificateRequest->info)) {
                $updateData['info'] = json_encode($validationInfo);
            }

            // Update if we have data
            if (!empty($updateData)) {
                $certificateRequest->update($updateData);
                
                Log::info('Updated certificate request with AI analysis data', [
                    'certificate_request_id' => $certificateRequestId,
                    'updated_fields' => array_keys($updateData)
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error updating certificate request from analysis', [
                'error' => $e->getMessage(),
                'certificate_request_id' => $certificateRequestId
            ]);
        }
    }

    /**
     * Store comprehensive analysis results
     *
     * @param int $certificateRequestId
     * @param array $analysisResults
     * @return void
     */
    private static function storeComprehensiveResults(int $certificateRequestId, array $analysisResults): void
    {
        try {
            $startTime = microtime(true);
            
            // Extract validation data from results
            $validationSummary = $analysisResults['validation_summary'] ?? [];
            $validationErrors = $analysisResults['validation_errors'] ?? [];
            $extractedData = $analysisResults['extracted_data'] ?? [];
            
            // Create comprehensive analysis record
            DocumentAnalysisResult::create([
                'certificate_request_id' => $certificateRequestId,
                'analysis_results' => $analysisResults,
                'validation_summary' => $validationSummary,
                'validation_errors' => $validationErrors,
                'extracted_data' => $extractedData,
                'overall_valid' => $validationSummary['overall_valid'] ?? false,
                'rut_found' => $validationSummary['rut_found'] ?? false,
                'person_type' => $validationSummary['person_type'] ?? null,
                'chamber_commerce_required' => $validationSummary['chamber_commerce_required'] ?? false,
                'chamber_commerce_found' => $validationSummary['chamber_commerce_found'] ?? false,
                'chamber_commerce_valid_date' => $validationSummary['chamber_commerce_valid_date'] ?? null,
                'cedula_found' => $validationSummary['cedula_found'] ?? false,
                'cedula_complete' => $validationSummary['cedula_complete'] ?? false,
                'legal_representative_match' => $validationSummary['legal_representative_match'] ?? null,
                'documents_processed' => count($analysisResults['documents'] ?? []),
                'processing_time' => microtime(true) - $startTime
            ]);
            
            Log::info('Comprehensive Document Analysis Results stored successfully', [
                'certificate_request_id' => $certificateRequestId,
                'validation_summary' => $validationSummary,
                'validation_errors' => $validationErrors,
                'documents_processed' => count($analysisResults['documents'] ?? []),
                'overall_valid' => $validationSummary['overall_valid'] ?? false
            ]);

        } catch (Exception $e) {
            Log::error('Error storing comprehensive analysis results', [
                'error' => $e->getMessage(),
                'certificate_request_id' => $certificateRequestId
            ]);
        }
    }

    /**
     * Process multiple certificate requests in batch
     * 
     * @param array $certificateRequestIds
     * @param int $userId
     * @param array $options
     * @return void
     */
    public static function processBatchCertificates(array $certificateRequestIds, int $userId, array $options = []): void
    {
        foreach ($certificateRequestIds as $certificateRequestId) {
            try {
                // Dispatch individual jobs with delay to prevent overwhelming the system
                ProcessCertificateJob::dispatch(
                    null, // No specific file path - will process all documents
                    $userId,
                    $certificateRequestId,
                    array_merge($options, ['comprehensive_analysis' => true])
                )->delay(now()->addSeconds(rand(1, 10)));
                
                Log::info("Dispatched batch processing job", [
                    'certificate_request_id' => $certificateRequestId,
                    'user_id' => $userId
                ]);
                
            } catch (Exception $e) {
                Log::error("Error dispatching batch job for certificate request", [
                    'certificate_request_id' => $certificateRequestId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Process certificate requests from the last N days
     * 
     * @param int $days
     * @param int $userId
     * @param int $limit
     * @param array $options
     * @return void
     */
    public static function processRecentCertificates(int $days = 7, int $userId = 1, int $limit = 10, array $options = []): void
    {
        try {
            $certificateRequests = CertificateRequest::where('updated_at', '>=', now()->subDays($days))
                ->whereDoesntHave('documentAnalysisResults') // Only process unanalyzed ones
                ->limit($limit)
                ->pluck('id')
                ->toArray();

            if (empty($certificateRequests)) {
                Log::info("No recent unanalyzed certificate requests found", [
                    'days' => $days,
                    'limit' => $limit
                ]);
                return;
            }

            Log::info("Processing recent certificate requests", [
                'count' => count($certificateRequests),
                'days' => $days,
                'certificate_ids' => $certificateRequests
            ]);

            static::processBatchCertificates($certificateRequests, $userId, $options);

        } catch (Exception $e) {
            Log::error("Error processing recent certificates", [
                'error' => $e->getMessage(),
                'days' => $days,
                'limit' => $limit
            ]);
        }
    }

    /**
     * Reprocess failed or incomplete analyses
     * 
     * @param int $userId
     * @param int $limit
     * @param array $options
     * @return void
     */
    public static function reprocessFailedAnalyses(int $userId = 1, int $limit = 5, array $options = []): void
    {
        try {
            // Find certificate requests with failed or incomplete analyses
            $failedCertificates = CertificateRequest::whereHas('documentAnalysisResults', function($query) {
                $query->where('overall_valid', false)
                      ->orWhereNull('analysis_results')
                      ->orWhere('documents_processed', 0);
            })->limit($limit)->pluck('id')->toArray();

            if (empty($failedCertificates)) {
                Log::info("No failed analyses found to reprocess");
                return;
            }

            Log::info("Reprocessing failed analyses", [
                'count' => count($failedCertificates),
                'certificate_ids' => $failedCertificates
            ]);

            static::processBatchCertificates($failedCertificates, $userId, array_merge($options, [
                'reprocess' => true
            ]));

        } catch (Exception $e) {
            Log::error("Error reprocessing failed analyses", [
                'error' => $e->getMessage(),
                'limit' => $limit
            ]);
        }
    }

    /**
     * Get analysis statistics
     * 
     * @return array
     */
    public static function getAnalysisStatistics(): array
    {
        try {
            return [
                'total_certificates' => CertificateRequest::count(),
                'analyzed_certificates' => DocumentAnalysisResult::distinct('certificate_request_id')->count(),
                'valid_analyses' => DocumentAnalysisResult::where('overall_valid', true)->count(),
                'invalid_analyses' => DocumentAnalysisResult::where('overall_valid', false)->count(),
                'natural_persons' => DocumentAnalysisResult::where('person_type', 'natural')->count(),
                'juridical_persons' => DocumentAnalysisResult::where('person_type', 'juridica')->count(),
                'pending_analysis' => CertificateRequest::whereDoesntHave('documentAnalysisResults')->count(),
                'total_files' => FileManager::where('document_type', 'ATTACHED')->count(),
                'recent_analyses' => DocumentAnalysisResult::where('created_at', '>=', now()->subDays(7))->count()
            ];
        } catch (Exception $e) {
            Log::error("Error getting analysis statistics", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
