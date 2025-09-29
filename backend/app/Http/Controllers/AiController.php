<?php

namespace App\Http\Controllers;

use App\Services\OcrService;
use App\Services\AiContentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;

class AiController extends Controller
{
    private $ocrService;
    private $aiContentService;

    public function __construct(OcrService $ocrService, AiContentService $aiContentService)
    {
        $this->ocrService = $ocrService;
        $this->aiContentService = $aiContentService;
    }

    /**
     * Process certificate image and extract data
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function processCertificateImage(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'image' => 'required|file|mimes:jpeg,jpg,png,pdf|max:' . (config('ai.processing.max_file_size') / 1024),
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Store the uploaded file temporarily
            $file = $request->file('image');
            $filename = 'temp_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('temp', $filename, 'local');
            $fullPath = storage_path('app/' . $filePath);

            try {
                // Extract text using OCR
                $ocrResult = $this->ocrService->extractDocumentData($fullPath);
                
                if (!$ocrResult['success']) {
                    return response()->json($ocrResult, 500);
                }

                // Analyze the extracted text with AI
                $aiAnalysis = $this->aiContentService->analyzeCertificateText($ocrResult['data']['full_text']);

                // Classify the document
                $classification = $this->aiContentService->classifyDocument($ocrResult['data']['full_text']);

                // Combine results
                $result = [
                    'success' => true,
                    'message' => 'Certificate processed successfully',
                    'data' => [
                        'ocr_results' => $ocrResult['data'],
                        'ai_analysis' => $aiAnalysis['success'] ? $aiAnalysis['data'] : null,
                        'classification' => $classification['success'] ? $classification['data'] : null,
                        'processing_info' => [
                            'file_size' => $file->getSize(),
                            'file_type' => $file->getClientMimeType(),
                            'original_name' => $file->getClientOriginalName(),
                            'processed_at' => now()->toISOString()
                        ]
                    ]
                ];

                return response()->json($result);

            } finally {
                // Clean up temporary file
                if (Storage::disk('local')->exists($filePath)) {
                    Storage::disk('local')->delete($filePath);
                }
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing certificate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate email content for certificate
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateEmailContent(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'certificate_data' => 'required|array',
                'recipient_name' => 'required|string|max:255',
                'email_type' => 'required|in:notification,reminder,congratulations'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->aiContentService->generateEmailContent(
                $request->certificate_data,
                $request->recipient_name,
                $request->email_type
            );

            return response()->json($result);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating email content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Classify document type
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function classifyDocument(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'text' => 'required|string|min:10'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->aiContentService->classifyDocument($request->text);

            return response()->json($result);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error classifying document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract text from image (OCR only)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function extractText(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'image' => 'required|file|mimes:jpeg,jpg,png,pdf|max:' . (config('ai.processing.max_file_size') / 1024),
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Store the uploaded file temporarily
            $file = $request->file('image');
            $filename = 'temp_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('temp', $filename, 'local');
            $fullPath = storage_path('app/' . $filePath);

            try {
                $result = $this->ocrService->extractTextFromImage($fullPath);
                return response()->json($result);

            } finally {
                // Clean up temporary file
                if (Storage::disk('local')->exists($filePath)) {
                    Storage::disk('local')->delete($filePath);
                }
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error extracting text: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI service status and configuration
     *
     * @return JsonResponse
     */
    public function getServiceStatus(): JsonResponse
    {
        try {
            $status = [
                'success' => true,
                'message' => 'AI services status',
                'data' => [
                    'ocr_service' => [
                        'enabled' => !empty(config('ai.google_vision.project_id')),
                        'provider' => 'Google Cloud Vision'
                    ],
                    'ai_content_service' => [
                        'enabled' => !empty(config('ai.gemini.api_key')),
                        'provider' => 'Google Gemini',
                        'model' => config('ai.gemini.model')
                    ],
                    'processing_limits' => [
                        'max_file_size_mb' => config('ai.processing.max_file_size') / 1048576,
                        'supported_formats' => config('ai.processing.supported_formats'),
                        'timeout_seconds' => config('ai.processing.timeout')
                    ]
                ]
            ];

            return response()->json($status);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting service status: ' . $e->getMessage()
            ], 500);
        }
    }
}
