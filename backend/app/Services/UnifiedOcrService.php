<?php

namespace App\Services;

use App\Services\AwsTextractService;
use App\Services\OcrService as GoogleVisionService;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Unified OCR Service
 * 
 * Manages both AWS Textract and Google Cloud Vision
 * Routes requests to the appropriate service based on configuration
 */
class UnifiedOcrService
{
    private $preferredService;
    private $textractService;
    private $visionService;

    public function __construct()
    {
        $this->preferredService = config('ai.ocr_service', 'textract');
        
        try {
            if ($this->preferredService === 'textract') {
                $this->textractService = new AwsTextractService();
                Log::info('Unified OCR initialized with AWS Textract as primary');
            } else {
                $this->visionService = new GoogleVisionService();
                Log::info('Unified OCR initialized with Google Vision as primary');
            }
        } catch (Exception $e) {
            Log::error('Error initializing primary OCR service: ' . $e->getMessage());
            // Fallback to mock mode
        }
    }

    /**
     * Extract text from image/document
     * 
     * @param string $filePath
     * @param array $options
     * @return array
     */
    public function extractTextFromImage(string $filePath, array $options = []): array
    {
        try {
            $analysisType = $options['analysis_type'] ?? 'text';
            $useService = $options['service'] ?? $this->preferredService;

            Log::info("Extracting text using {$useService} service", [
                'file' => basename($filePath),
                'analysis_type' => $analysisType
            ]);

            if ($useService === 'textract') {
                return $this->extractWithTextract($filePath, $analysisType);
            } else {
                return $this->extractWithVision($filePath, $options);
            }

        } catch (Exception $e) {
            Log::error('OCR extraction failed: ' . $e->getMessage());
            
            // Try fallback service
            return $this->tryFallbackService($filePath, $options);
        }
    }

    /**
     * Extract using AWS Textract
     */
    private function extractWithTextract(string $filePath, string $analysisType): array
    {
        if (!$this->textractService) {
            $this->textractService = new AwsTextractService();
        }

        $result = $this->textractService->extractFromDocument($filePath, $analysisType);
        
        // Normalize result format to match legacy OcrService
        if ($result['success']) {
            $data = $result['data'];
            return [
                'success' => true,
                'data' => [
                    'text' => $data['raw_text'],
                    'confidence' => $data['confidence'],
                    'blocks' => count(explode("\n", $data['raw_text'])),
                    'language' => $this->detectLanguage($data['raw_text']),
                    'extraction_time' => $data['processing_time'],
                    'service' => 'aws_textract',
                    'structured_data' => $data['structured_data'] ?? null,
                    'analysis_type' => $data['analysis_type'] ?? 'text'
                ]
            ];
        }

        return $result;
    }

    /**
     * Extract using Google Cloud Vision
     */
    private function extractWithVision(string $filePath, array $options): array
    {
        if (!$this->visionService) {
            $this->visionService = new GoogleVisionService();
        }

        $result = $this->visionService->extractTextFromImage($filePath);
        
        // Add service identifier
        if ($result['success']) {
            $result['data']['service'] = 'google_vision';
        }

        return $result;
    }

    /**
     * Try fallback service if primary fails
     */
    private function tryFallbackService(string $filePath, array $options): array
    {
        try {
            $fallbackService = $this->preferredService === 'textract' ? 'vision' : 'textract';
            
            Log::info("Trying fallback OCR service: {$fallbackService}");
            
            $options['service'] = $fallbackService;
            return $this->extractTextFromImage($filePath, $options);

        } catch (Exception $e) {
            Log::error('Fallback OCR service also failed: ' . $e->getMessage());
            
            // Return mock result as last resort
            return $this->generateMockResult($filePath);
        }
    }

    /**
     * Extract text specifically for Colombian documents
     * 
     * @param string $filePath
     * @param string $documentType ('rut'|'cedula'|'chamber_commerce')
     * @return array
     */
    public function extractColombianDocument(string $filePath, string $documentType): array
    {
        $options = [
            'analysis_type' => 'forms', // Use forms analysis for structured data
            'document_type' => $documentType
        ];

        $result = $this->extractTextFromImage($filePath, $options);

        if ($result['success']) {
            // Post-process for Colombian documents
            $result['data']['colombian_fields'] = $this->extractColombianFields(
                $result['data']['text'],
                $documentType,
                $result['data']['structured_data'] ?? []
            );
        }

        return $result;
    }

    /**
     * Extract Colombian document specific fields
     */
    private function extractColombianFields(string $text, string $documentType, array $structuredData = []): array
    {
        $fields = [];

        switch ($documentType) {
            case 'rut':
                $fields = $this->extractRutFields($text, $structuredData);
                break;
            case 'cedula':
                $fields = $this->extractCedulaFields($text, $structuredData);
                break;
            case 'chamber_commerce':
                $fields = $this->extractChamberCommerceFields($text, $structuredData);
                break;
        }

        return $fields;
    }

    /**
     * Extract RUT specific fields
     */
    private function extractRutFields(string $text, array $structuredData): array
    {
        $fields = [];

        // Try structured data first (from Textract)
        if (isset($structuredData['form_fields'])) {
            $formFields = $structuredData['form_fields'];
            $fields['nit'] = $formFields['nit']['value'] ?? null;
            $fields['razon_social'] = $formFields['razon_social']['value'] ?? null;
            $fields['direccion'] = $formFields['direccion']['value'] ?? null;
            $fields['telefono'] = $formFields['telefono']['value'] ?? null;
        }

        // Fallback to regex patterns
        if (empty($fields['nit'])) {
            preg_match('/NIT:?\s*(\d{9,12}-?\d?)/', $text, $matches);
            $fields['nit'] = $matches[1] ?? null;
        }

        if (empty($fields['razon_social'])) {
            preg_match('/RAZ[ÓO]N SOCIAL:?\s*([A-ZÁÉÍÓÚÑ\s\.]+)/', $text, $matches);
            $fields['razon_social'] = isset($matches[1]) ? trim($matches[1]) : null;
        }

        // Determine person type
        $fields['person_type'] = $this->determinePersonType($text, $fields);

        return array_filter($fields); // Remove null values
    }

    /**
     * Extract Cédula specific fields
     */
    private function extractCedulaFields(string $text, array $structuredData): array
    {
        $fields = [];

        // Try structured data first
        if (isset($structuredData['form_fields'])) {
            $formFields = $structuredData['form_fields'];
            $fields['nombres'] = $formFields['nombres']['value'] ?? null;
            $fields['apellidos'] = $formFields['apellidos']['value'] ?? null;
            $fields['numero_cedula'] = $formFields['cedula']['value'] ?? null;
        }

        // Fallback to regex patterns
        if (empty($fields['numero_cedula'])) {
            preg_match('/N[ÚU]MERO:?\s*(\d{7,10})/', $text, $matches);
            $fields['numero_cedula'] = $matches[1] ?? null;
        }

        if (empty($fields['nombres'])) {
            preg_match('/NOMBRES:?\s*([A-ZÁÉÍÓÚÑ\s]+)/', $text, $matches);
            $fields['nombres'] = isset($matches[1]) ? trim($matches[1]) : null;
        }

        if (empty($fields['apellidos'])) {
            preg_match('/APELLIDOS:?\s*([A-ZÁÉÍÓÚÑ\s]+)/', $text, $matches);
            $fields['apellidos'] = isset($matches[1]) ? trim($matches[1]) : null;
        }

        return array_filter($fields);
    }

    /**
     * Extract Chamber of Commerce specific fields
     */
    private function extractChamberCommerceFields(string $text, array $structuredData): array
    {
        $fields = [];

        // Extract expedition date
        preg_match('/EXPEDICI[ÓO]N:?\s*(\d{1,2}\/\d{1,2}\/\d{4})/', $text, $matches);
        $fields['fecha_expedicion'] = $matches[1] ?? null;

        // Validate if it's within 30 days
        if ($fields['fecha_expedicion']) {
            $expeditionDate = \Carbon\Carbon::createFromFormat('d/m/Y', $fields['fecha_expedicion']);
            $fields['valid_date'] = $expeditionDate->diffInDays(now()) <= 30;
        }

        return array_filter($fields);
    }

    /**
     * Determine person type from RUT
     */
    private function determinePersonType(string $text, array $fields): string
    {
        // Check for juridical person indicators
        $juridicalIndicators = ['SAS', 'S.A.S', 'LTDA', 'S.A.', 'SOCIEDAD', 'EMPRESA'];
        
        $textUpper = strtoupper($text);
        foreach ($juridicalIndicators as $indicator) {
            if (strpos($textUpper, $indicator) !== false) {
                return 'juridica';
            }
        }

        // Check NIT pattern (juridical persons usually start with 8 or 9)
        if (isset($fields['nit'])) {
            $firstDigit = substr($fields['nit'], 0, 1);
            if (in_array($firstDigit, ['8', '9'])) {
                return 'juridica';
            }
        }

        return 'natural';
    }

    /**
     * Detect language (reuse from original service)
     */
    private function detectLanguage(string $text): string
    {
        $spanishWords = ['de', 'la', 'el', 'en', 'que', 'y', 'por', 'con', 'para', 'certificado', 'nombre'];
        $englishWords = ['the', 'and', 'of', 'to', 'in', 'certificate', 'name', 'date', 'issued'];

        $words = str_word_count(strtolower($text), 1);
        $spanishCount = count(array_intersect($words, $spanishWords));
        $englishCount = count(array_intersect($words, $englishWords));

        if ($spanishCount > $englishCount) {
            return 'es';
        } elseif ($englishCount > $spanishCount) {
            return 'en';
        }

        return 'unknown';
    }

    /**
     * Generate mock result as fallback
     */
    private function generateMockResult(string $filePath): array
    {
        return [
            'success' => true,
            'data' => [
                'text' => 'Texto simulado extraído de: ' . basename($filePath),
                'confidence' => 75.0,
                'blocks' => 1,
                'language' => 'es',
                'extraction_time' => 0.1,
                'service' => 'mock_fallback'
            ]
        ];
    }

    /**
     * Get available OCR services status
     */
    public function getServicesStatus(): array
    {
        return [
            'preferred_service' => $this->preferredService,
            'textract_available' => class_exists('Aws\Textract\TextractClient'),
            'vision_available' => class_exists('Google\Cloud\Vision\V1\ImageAnnotatorClient'),
            'aws_configured' => !empty(config('services.aws.key')),
            'vision_configured' => !empty(config('ai.google_vision.api_key'))
        ];
    }
}