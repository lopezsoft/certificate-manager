<?php

namespace App\Services;

use App\Services\OcrService;
use App\Services\AiContentService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class DocumentAnalysisService
{
    private $ocrService;
    private $aiContentService;

    public function __construct(OcrService $ocrService, AiContentService $aiContentService)
    {
        $this->ocrService = $ocrService;
        $this->aiContentService = $aiContentService;
    }

    /**
     * Analyze all documents for a certificate request
     *
     * @param array $documentPaths Array of file paths to analyze
     * @param int $certificateRequestId
     * @return array
     */
    public function analyzeDocumentSet(array $documentPaths, int $certificateRequestId): array
    {
        try {
            Log::info("Starting comprehensive document analysis", [
                'certificate_request_id' => $certificateRequestId,
                'document_count' => count($documentPaths)
            ]);

            $analysisResults = [
                'certificate_request_id' => $certificateRequestId,
                'documents' => [],
                'validation_summary' => [
                    'rut_found' => false,
                    'person_type' => null, // 'natural' or 'juridica'
                    'chamber_commerce_required' => false,
                    'chamber_commerce_found' => false,
                    'chamber_commerce_valid_date' => false,
                    'cedula_found' => false,
                    'cedula_complete' => false,
                    'legal_representative_match' => false,
                    'overall_valid' => false
                ],
                'extracted_data' => [
                    'rut' => null,
                    'chamber_commerce' => null,
                    'cedula' => null,
                    'legal_representative' => null
                ],
                'validation_errors' => [],
                'processed_at' => now()->toISOString()
            ];

            // Step 1: Analyze each document individually
            foreach ($documentPaths as $index => $filePath) {
                $documentAnalysis = $this->analyzeSingleDocument($filePath, $index);
                $analysisResults['documents'][] = $documentAnalysis;
            }

            // Step 2: Identify document types and extract data
            $this->identifyDocumentTypes($analysisResults);

            // Step 3: Perform comprehensive validation
            $this->performComprehensiveValidation($analysisResults);

            Log::info("Document analysis completed", [
                'certificate_request_id' => $certificateRequestId,
                'overall_valid' => $analysisResults['validation_summary']['overall_valid']
            ]);

            return $analysisResults;

        } catch (Exception $e) {
            Log::error("Error in document analysis", [
                'error' => $e->getMessage(),
                'certificate_request_id' => $certificateRequestId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'certificate_request_id' => $certificateRequestId
            ];
        }
    }

    /**
     * Analyze a single document
     *
     * @param string $filePath
     * @param int $index
     * @return array
     */
    private function analyzeSingleDocument(string $filePath, int $index): array
    {
        try {
            // Extract text using OCR
            $ocrResult = $this->ocrService->extractDocumentData($filePath);
            
            if (!$ocrResult['success']) {
                throw new Exception("OCR failed for document {$index}: " . $ocrResult['message']);
            }

            $extractedText = $ocrResult['data']['full_text'];

            // Classify document type
            $classification = $this->classifyColombianDocument($extractedText);

            // Extract specific data based on document type
            $specificData = $this->extractDocumentSpecificData($extractedText, $classification['document_type']);

            return [
                'index' => $index,
                'file_path' => $filePath,
                'ocr_result' => $ocrResult['data'],
                'classification' => $classification,
                'specific_data' => $specificData,
                'analysis_success' => true
            ];

        } catch (Exception $e) {
            Log::error("Error analyzing single document", [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
                'index' => $index
            ]);

            return [
                'index' => $index,
                'file_path' => $filePath,
                'analysis_success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Classify Colombian documents (RUT, Cédula, Cámara de Comercio)
     *
     * @param string $text
     * @return array
     */
    private function classifyColombianDocument(string $text): array
    {
        try {
            $prompt = "Analiza el siguiente texto extraído de un documento colombiano y clasifícalo en una de estas categorías:

1. RUT: Registro Único Tributario de la DIAN
2. CEDULA: Cédula de ciudadanía colombiana
3. CAMARA_COMERCIO: Certificado de existencia y representación legal de Cámara de Comercio
4. OTRO: Si no encaja en las categorías anteriores

Busca palabras clave como:
- RUT: 'DIAN', 'Registro Único Tributario', 'NIT', 'Actividad Económica'
- CEDULA: 'República de Colombia', 'Cédula de Ciudadanía', 'Registraduría'
- CAMARA_COMERCIO: 'Cámara de Comercio', 'Certificado de Existencia', 'Representación Legal', 'Matrícula'

Responde SOLO con la categoría (RUT, CEDULA, CAMARA_COMERCIO, OTRO), sin texto adicional.

Texto del documento:
{$text}";

            $response = $this->aiContentService->generateSimpleResponse($prompt);
            
            $documentType = strtoupper(trim($response));
            
            // Validate response
            $validTypes = ['RUT', 'CEDULA', 'CAMARA_COMERCIO', 'OTRO'];
            if (!in_array($documentType, $validTypes)) {
                $documentType = 'OTRO';
            }

            return [
                'document_type' => $documentType,
                'confidence' => 0.9 // You can implement confidence calculation
            ];

        } catch (Exception $e) {
            Log::error("Error classifying document", ['error' => $e->getMessage()]);
            return [
                'document_type' => 'OTRO',
                'confidence' => 0.0
            ];
        }
    }

    /**
     * Extract specific data based on document type
     *
     * @param string $text
     * @param string $documentType
     * @return array
     */
    private function extractDocumentSpecificData(string $text, string $documentType): array
    {
        switch ($documentType) {
            case 'RUT':
                return $this->extractRutData($text);
            case 'CEDULA':
                return $this->extractCedulaData($text);
            case 'CAMARA_COMERCIO':
                return $this->extractCamaraComercioData($text);
            default:
                return [];
        }
    }

    /**
     * Extract RUT specific data
     *
     * @param string $text
     * @return array
     */
    private function extractRutData(string $text): array
    {
        try {
            $prompt = "Analiza este RUT colombiano y extrae la siguiente información en formato JSON:

{
    \"nit\": \"Número de identificación tributaria\",
    \"dv\": \"Dígito de verificación\",
    \"razon_social\": \"Razón social o nombres completos\",
    \"tipo_persona\": \"NATURAL o JURIDICA\",
    \"actividad_economica\": \"Actividad económica principal\",
    \"direccion\": \"Dirección\",
    \"telefono\": \"Teléfono si está disponible\",
    \"email\": \"Email si está disponible\",
    \"representante_legal\": \"Nombre del representante legal (solo si es persona jurídica)\",
    \"cedula_representante\": \"Cédula del representante legal (solo si es persona jurídica)\"
}

Si no encuentras algún campo, usa null. Responde SOLO con el JSON válido.

Texto del RUT:
{$text}";

            $response = $this->aiContentService->generateSimpleResponse($prompt);
            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON response from AI");
            }

            return $data;

        } catch (Exception $e) {
            Log::error("Error extracting RUT data", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Extract Cédula specific data
     *
     * @param string $text
     * @return array
     */
    private function extractCedulaData(string $text): array
    {
        try {
            $prompt = "Analiza esta cédula de ciudadanía colombiana y extrae la siguiente información en formato JSON:

{
    \"numero_cedula\": \"Número de la cédula\",
    \"nombres\": \"Nombres completos\",
    \"apellidos\": \"Apellidos completos\",
    \"fecha_nacimiento\": \"Fecha de nacimientoen formato YYYY-MM-DD si es posible\",
    \"lugar_nacimiento\": \"Lugar de nacimiento\",
    \"fecha_expedicion\": \"Fecha de expedición en formato YYYY-MM-DD si es posible\",
    \"lugar_expedicion\": \"Lugar de expedición\",
    \"sangre\": \"Tipo de sangre si está visible\",
    \"sexo\": \"M o F\",
    \"estado_civil\": \"Estado civil si está visible\",
    \"documento_completo\": \"Indica true si parece que tienes información de ambos lados de la cédula, false si solo un lado\"
}

Si no encuentras algún campo, usa null. Responde SOLO con el JSON válido.

Texto de la cédula:
{$text}";

            $response = $this->aiContentService->generateSimpleResponse($prompt);
            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON response from AI");
            }

            return $data;

        } catch (Exception $e) {
            Log::error("Error extracting Cédula data", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Extract Cámara de Comercio specific data
     *
     * @param string $text
     * @return array
     */
    private function extractCamaraComercioData(string $text): array
    {
        try {
            $prompt = "Analiza este certificado de existencia y representación legal de Cámara de Comercio y extrae la siguiente información en formato JSON:

{
    \"razon_social\": \"Razón social de la empresa\",
    \"nit\": \"NIT de la empresa\",
    \"numero_matricula\": \"Número de matrícula mercantil\",
    \"fecha_expedicion\": \"Fecha de expedición del certificado en formato YYYY-MM-DD\",
    \"fecha_constitucion\": \"Fecha de constitución de la empresa en formato YYYY-MM-DD si está disponible\",
    \"representante_legal\": \"Nombre del representante legal\",
    \"cedula_representante\": \"Cédula del representante legal\",
    \"objeto_social\": \"Objeto social de la empresa\",
    \"direccion\": \"Dirección de la empresa\",
    \"telefono\": \"Teléfono si está disponible\",
    \"capital_social\": \"Capital social si está disponible\",
    \"camara_comercio\": \"Nombre de la Cámara de Comercio que expide\"
}

Si no encuentras algún campo, usa null. Responde SOLO con el JSON válido.

Texto del certificado:
{$text}";

            $response = $this->aiContentService->generateSimpleResponse($prompt);
            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON response from AI");
            }

            return $data;

        } catch (Exception $e) {
            Log::error("Error extracting Cámara de Comercio data", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Identify document types from analysis results
     *
     * @param array &$analysisResults
     */
    private function identifyDocumentTypes(array &$analysisResults): void
    {
        foreach ($analysisResults['documents'] as $document) {
            if (!$document['analysis_success']) continue;

            $docType = $document['classification']['document_type'];
            
            switch ($docType) {
                case 'RUT':
                    $analysisResults['validation_summary']['rut_found'] = true;
                    $analysisResults['extracted_data']['rut'] = $document['specific_data'];
                    
                    if (isset($document['specific_data']['tipo_persona'])) {
                        $analysisResults['validation_summary']['person_type'] = 
                            strtolower($document['specific_data']['tipo_persona']);
                    }
                    break;

                case 'CAMARA_COMERCIO':
                    $analysisResults['validation_summary']['chamber_commerce_found'] = true;
                    $analysisResults['extracted_data']['chamber_commerce'] = $document['specific_data'];
                    break;

                case 'CEDULA':
                    $analysisResults['validation_summary']['cedula_found'] = true;
                    $analysisResults['extracted_data']['cedula'] = $document['specific_data'];
                    
                    if (isset($document['specific_data']['documento_completo'])) {
                        $analysisResults['validation_summary']['cedula_complete'] = 
                            $document['specific_data']['documento_completo'];
                    }
                    break;
            }
        }
    }

    /**
     * Perform comprehensive validation of all documents
     *
     * @param array &$analysisResults
     */
    private function performComprehensiveValidation(array &$analysisResults): void
    {
        $errors = [];

        // 1. RUT is required
        if (!$analysisResults['validation_summary']['rut_found']) {
            $errors[] = "RUT no encontrado. Es requerido para continuar.";
        }

        // 2. If jurídica, chamber of commerce is required
        if ($analysisResults['validation_summary']['person_type'] === 'juridica') {
            $analysisResults['validation_summary']['chamber_commerce_required'] = true;
            
            if (!$analysisResults['validation_summary']['chamber_commerce_found']) {
                $errors[] = "Para personas jurídicas se requiere Certificado de Cámara de Comercio.";
            } else {
                // Validate chamber commerce date (max 30 days)
                $this->validateChamberCommerceDate($analysisResults, $errors);
            }
        }

        // 3. Validate cédula
        $this->validateCedula($analysisResults, $errors);

        // 4. Validate legal representative match
        $this->validateLegalRepresentativeMatch($analysisResults, $errors);

        $analysisResults['validation_errors'] = $errors;
        $analysisResults['validation_summary']['overall_valid'] = empty($errors);
    }

    /**
     * Validate chamber of commerce date
     *
     * @param array &$analysisResults
     * @param array &$errors
     */
    private function validateChamberCommerceDate(array &$analysisResults, array &$errors): void
    {
        $chamberData = $analysisResults['extracted_data']['chamber_commerce'];
        
        if (!isset($chamberData['fecha_expedicion']) || empty($chamberData['fecha_expedicion'])) {
            $errors[] = "No se pudo determinar la fecha de expedición del Certificado de Cámara de Comercio.";
            return;
        }

        try {
            $expeditionDate = Carbon::parse($chamberData['fecha_expedicion']);
            $daysDifference = $expeditionDate->diffInDays(now());

            if ($daysDifference > 30) {
                $errors[] = "El Certificado de Cámara de Comercio tiene más de 30 días de expedición ({$daysDifference} días).";
                $analysisResults['validation_summary']['chamber_commerce_valid_date'] = false;
            } else {
                $analysisResults['validation_summary']['chamber_commerce_valid_date'] = true;
            }
        } catch (Exception $e) {
            $errors[] = "Fecha de expedición del Certificado de Cámara de Comercio no válida.";
        }
    }

    /**
     * Validate cédula completeness and legibility
     *
     * @param array &$analysisResults
     * @param array &$errors
     */
    private function validateCedula(array &$analysisResults, array &$errors): void
    {
        if (!$analysisResults['validation_summary']['cedula_found']) {
            $errors[] = "Cédula de ciudadanía no encontrada.";
            return;
        }

        if (!$analysisResults['validation_summary']['cedula_complete']) {
            $errors[] = "La cédula parece estar incompleta. Se requieren ambos lados del documento.";
        }

        $cedulaData = $analysisResults['extracted_data']['cedula'];
        
        // Check essential fields
        $requiredFields = ['numero_cedula', 'nombres', 'apellidos'];
        foreach ($requiredFields as $field) {
            if (!isset($cedulaData[$field]) || empty($cedulaData[$field])) {
                $errors[] = "La información de la cédula no es completamente legible (falta: {$field}).";
            }
        }
    }

    /**
     * Validate legal representative match between RUT and cédula
     *
     * @param array &$analysisResults
     * @param array &$errors
     */
    private function validateLegalRepresentativeMatch(array &$analysisResults, array &$errors): void
    {
        $rutData = $analysisResults['extracted_data']['rut'];
        $cedulaData = $analysisResults['extracted_data']['cedula'];

        if (!$rutData || !$cedulaData) {
            return; // Can't validate without both documents
        }

        // For jurídica person, validate representative match
        if ($analysisResults['validation_summary']['person_type'] === 'juridica') {
            if (!isset($rutData['representante_legal']) || !isset($rutData['cedula_representante'])) {
                $errors[] = "No se encontró información del representante legal en el RUT.";
                return;
            }

            // Compare names (simplified comparison)
            $rutRepresentative = strtoupper(trim($rutData['representante_legal']));
            $cedulaFullName = strtoupper(trim(($cedulaData['nombres'] ?? '') . ' ' . ($cedulaData['apellidos'] ?? '')));

            if ($this->compareNames($rutRepresentative, $cedulaFullName)) {
                $analysisResults['validation_summary']['legal_representative_match'] = true;
            } else {
                $errors[] = "El nombre en la cédula no coincide con el representante legal del RUT.";
            }

            // Compare cédula numbers
            if (isset($rutData['cedula_representante']) && isset($cedulaData['numero_cedula'])) {
                $rutCedula = preg_replace('/\D/', '', $rutData['cedula_representante']);
                $cedulaNumber = preg_replace('/\D/', '', $cedulaData['numero_cedula']);

                if ($rutCedula !== $cedulaNumber) {
                    $errors[] = "El número de cédula no coincide entre el RUT y la cédula presentada.";
                }
            }
        }
    }

    /**
     * Compare names with some tolerance for OCR errors
     *
     * @param string $name1
     * @param string $name2
     * @return bool
     */
    private function compareNames(string $name1, string $name2): bool
    {
        // Simple comparison - you can improve this with fuzzy matching
        $name1 = preg_replace('/\s+/', ' ', $name1);
        $name2 = preg_replace('/\s+/', ' ', $name2);

        // Calculate similarity
        similar_text($name1, $name2, $percent);
        
        return $percent >= 80; // 80% similarity threshold
    }
}