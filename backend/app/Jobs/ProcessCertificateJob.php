<?php

namespace App\Jobs;

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
     * @param string $filePath
     * @param int $userId
     * @param int|null $requestId
     * @param array $options
     */
    public function __construct(string $filePath, int $userId, ?int $requestId = null, array $options = [])
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
        try {
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

            // Prepare results
            $results = [
                'ocr_results' => $ocrResult['data'],
                'ai_analysis' => $aiAnalysis['success'] ? $aiAnalysis['data'] : null,
                'classification' => $classification['success'] ? $classification['data'] : null,
                'processed_at' => now()->toISOString(),
                'processing_time' => microtime(true) - LARAVEL_START
            ];

            // Store results (customize based on your database structure)
            $this->storeResults($results);

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
                'request_id' => $this->requestId
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
}
