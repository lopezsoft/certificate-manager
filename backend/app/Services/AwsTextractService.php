<?php

namespace App\Services;

use Aws\Textract\TextractClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * AWS Textract OCR Service
 * 
 * Especializado en extracción de texto y datos de documentos
 * Mejor para formularios y documentos estructurados que Google Vision
 */
class AwsTextractService
{
    private $client;
    private $region;

    public function __construct()
    {
        try {
            $this->region = config('services.aws.textract_region', 'us-east-1');
            
            $this->client = new TextractClient([
                'version' => 'latest',
                'region' => $this->region,
                'credentials' => [
                    'key' => config('services.aws.key'),
                    'secret' => config('services.aws.secret'),
                ]
            ]);

            Log::info('AWS Textract client initialized successfully');

        } catch (Exception $e) {
            Log::error('Error initializing AWS Textract client: ' . $e->getMessage());
            
            // Modo mock para desarrollo
            $this->client = null;
            Log::info('AWS Textract initialized in mock mode');
        }
    }

    /**
     * Extract text from document using AWS Textract
     *
     * @param string $documentPath
     * @param string $analysisType ('text'|'forms'|'tables')
     * @return array
     */
    public function extractFromDocument(string $documentPath, string $analysisType = 'text'): array
    {
        try {
            if (!file_exists($documentPath)) {
                throw new Exception("Document file not found: {$documentPath}");
            }

            // Modo mock si no hay cliente real
            if ($this->client === null) {
                return $this->generateMockTextractResult($documentPath, $analysisType);
            }

            $fileContent = file_get_contents($documentPath);
            $startTime = microtime(true);

            switch ($analysisType) {
                case 'forms':
                    $result = $this->analyzeDocument($fileContent, ['FORMS']);
                    break;
                case 'tables':
                    $result = $this->analyzeDocument($fileContent, ['TABLES']);
                    break;
                case 'all':
                    $result = $this->analyzeDocument($fileContent, ['FORMS', 'TABLES']);
                    break;
                default:
                    $result = $this->detectDocumentText($fileContent);
            }

            $processingTime = microtime(true) - $startTime;

            return [
                'success' => true,
                'data' => [
                    'raw_text' => $this->extractTextFromResult($result),
                    'structured_data' => $this->extractStructuredData($result, $analysisType),
                    'confidence' => $this->calculateConfidence($result),
                    'processing_time' => $processingTime,
                    'analysis_type' => $analysisType,
                    'service' => 'aws_textract'
                ]
            ];

        } catch (AwsException $e) {
            Log::error("AWS Textract error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'AWS Textract error: ' . $e->getMessage(),
                'service' => 'aws_textract'
            ];

        } catch (Exception $e) {
            Log::error("Document extraction error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'service' => 'aws_textract'
            ];
        }
    }

    /**
     * Detect text in document (basic OCR)
     */
    private function detectDocumentText($fileContent)
    {
        return $this->client->detectDocumentText([
            'Document' => [
                'Bytes' => $fileContent
            ]
        ]);
    }

    /**
     * Analyze document with forms and tables
     */
    private function analyzeDocument($fileContent, $featureTypes)
    {
        return $this->client->analyzeDocument([
            'Document' => [
                'Bytes' => $fileContent
            ],
            'FeatureTypes' => $featureTypes
        ]);
    }

    /**
     * Extract plain text from Textract result
     */
    private function extractTextFromResult($result): string
    {
        $text = '';
        
        if (isset($result['Blocks'])) {
            foreach ($result['Blocks'] as $block) {
                if ($block['BlockType'] === 'LINE') {
                    $text .= $block['Text'] . "\n";
                }
            }
        }

        return trim($text);
    }

    /**
     * Extract structured data (forms, key-value pairs)
     */
    private function extractStructuredData($result, $analysisType): array
    {
        $structuredData = [
            'key_value_pairs' => [],
            'tables' => [],
            'form_fields' => []
        ];

        if (!isset($result['Blocks'])) {
            return $structuredData;
        }

        // Extraer pares clave-valor (formularios)
        if ($analysisType === 'forms' || $analysisType === 'all') {
            $structuredData['key_value_pairs'] = $this->extractKeyValuePairs($result['Blocks']);
            $structuredData['form_fields'] = $this->extractFormFields($result['Blocks']);
        }

        // Extraer tablas
        if ($analysisType === 'tables' || $analysisType === 'all') {
            $structuredData['tables'] = $this->extractTables($result['Blocks']);
        }

        return $structuredData;
    }

    /**
     * Extract key-value pairs from forms
     */
    private function extractKeyValuePairs($blocks): array
    {
        $keyValuePairs = [];
        $keyMap = [];
        $valueMap = [];

        // Construir mapas de bloques
        foreach ($blocks as $block) {
            if ($block['BlockType'] === 'KEY_VALUE_SET') {
                if (isset($block['EntityTypes']) && in_array('KEY', $block['EntityTypes'])) {
                    $keyMap[$block['Id']] = $block;
                } elseif (isset($block['EntityTypes']) && in_array('VALUE', $block['EntityTypes'])) {
                    $valueMap[$block['Id']] = $block;
                }
            }
        }

        // Emparejar claves con valores
        foreach ($keyMap as $keyId => $keyBlock) {
            if (isset($keyBlock['Relationships'])) {
                foreach ($keyBlock['Relationships'] as $relationship) {
                    if ($relationship['Type'] === 'VALUE') {
                        foreach ($relationship['Ids'] as $valueId) {
                            if (isset($valueMap[$valueId])) {
                                $keyText = $this->getTextFromBlock($keyBlock, $blocks);
                                $valueText = $this->getTextFromBlock($valueMap[$valueId], $blocks);
                                
                                $keyValuePairs[] = [
                                    'key' => trim($keyText),
                                    'value' => trim($valueText),
                                    'confidence' => ($keyBlock['Confidence'] + $valueMap[$valueId]['Confidence']) / 2
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $keyValuePairs;
    }

    /**
     * Extract form fields (for Colombian documents)
     */
    private function extractFormFields($blocks): array
    {
        $formFields = [];
        $keyValuePairs = $this->extractKeyValuePairs($blocks);

        // Mapear campos comunes de documentos colombianos
        $fieldMappings = [
            'nit' => ['nit', 'número de identificación tributaria', 'numero nit'],
            'razon_social' => ['razón social', 'razon social', 'nombre', 'empresa'],
            'direccion' => ['dirección', 'direccion', 'domicilio'],
            'telefono' => ['teléfono', 'telefono', 'tel', 'celular'],
            'cedula' => ['cédula', 'cedula', 'cc', 'documento'],
            'nombres' => ['nombres', 'primer nombre', 'nombre'],
            'apellidos' => ['apellidos', 'primer apellido', 'apellido'],
            'fecha_expedicion' => ['fecha expedición', 'fecha de expedición', 'expedido'],
            'fecha_nacimiento' => ['fecha nacimiento', 'fecha de nacimiento', 'nació']
        ];

        foreach ($keyValuePairs as $pair) {
            $keyLower = strtolower($pair['key']);
            
            foreach ($fieldMappings as $fieldName => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($keyLower, $keyword) !== false) {
                        $formFields[$fieldName] = [
                            'value' => $pair['value'],
                            'confidence' => $pair['confidence'],
                            'raw_key' => $pair['key']
                        ];
                        break 2;
                    }
                }
            }
        }

        return $formFields;
    }

    /**
     * Extract tables from document
     */
    private function extractTables($blocks): array
    {
        $tables = [];
        // Implementar extracción de tablas si es necesario
        // Por ahora retornamos array vacío
        return $tables;
    }

    /**
     * Get text content from a block
     */
    private function getTextFromBlock($block, $allBlocks): string
    {
        $text = '';
        
        if (isset($block['Relationships'])) {
            foreach ($block['Relationships'] as $relationship) {
                if ($relationship['Type'] === 'CHILD') {
                    foreach ($relationship['Ids'] as $childId) {
                        foreach ($allBlocks as $childBlock) {
                            if ($childBlock['Id'] === $childId && $childBlock['BlockType'] === 'WORD') {
                                $text .= $childBlock['Text'] . ' ';
                            }
                        }
                    }
                }
            }
        }

        return trim($text);
    }

    /**
     * Calculate average confidence from result
     */
    private function calculateConfidence($result): float
    {
        if (!isset($result['Blocks'])) {
            return 0.0;
        }

        $totalConfidence = 0;
        $blockCount = 0;

        foreach ($result['Blocks'] as $block) {
            if (isset($block['Confidence'])) {
                $totalConfidence += $block['Confidence'];
                $blockCount++;
            }
        }

        return $blockCount > 0 ? round($totalConfidence / $blockCount, 2) : 0.0;
    }

    /**
     * Generate mock result for development/testing
     */
    private function generateMockTextractResult(string $documentPath, string $analysisType): array
    {
        $filename = basename($documentPath);
        $startTime = microtime(true);
        
        $mockText = $this->generateMockTextByFilename($filename);
        $mockStructuredData = $this->generateMockStructuredData($filename, $analysisType);
        
        return [
            'success' => true,
            'data' => [
                'raw_text' => $mockText,
                'structured_data' => $mockStructuredData,
                'confidence' => 87.5,
                'processing_time' => microtime(true) - $startTime,
                'analysis_type' => $analysisType,
                'service' => 'aws_textract_mock'
            ]
        ];
    }

    /**
     * Generate mock text based on filename
     */
    private function generateMockTextByFilename(string $filename): string
    {
        $lower = strtolower($filename);
        
        if (strpos($lower, 'rut') !== false) {
            return "REGISTRO ÚNICO TRIBUTARIO - RUT\n" .
                   "DIAN - DIRECCIÓN DE IMPUESTOS Y ADUANAS NACIONALES\n" .
                   "NIT: 900123456-1\n" .
                   "RAZÓN SOCIAL: EMPRESA DEMO SAS\n" .
                   "DIRECCIÓN: CALLE 123 # 45-67 BOGOTÁ D.C.\n" .
                   "TELÉFONO: 601-234-5678\n" .
                   "ESTADO: ACTIVO\n" .
                   "RÉGIMEN: PERSONA JURÍDICA\n" .
                   "FECHA ACTUALIZACIÓN: " . now()->format('d/m/Y');
        }
        
        if (strpos($lower, 'cedula') !== false || strpos($lower, 'cc') !== false) {
            return "REPÚBLICA DE COLOMBIA\n" .
                   "REGISTRADURÍA NACIONAL DEL ESTADO CIVIL\n" .
                   "CÉDULA DE CIUDADANÍA\n" .
                   "NOMBRES: JUAN CARLOS\n" .
                   "APELLIDOS: PÉREZ GARCÍA\n" .
                   "NÚMERO: 12345678\n" .
                   "FECHA NACIMIENTO: 15/03/1985\n" .
                   "LUGAR NACIMIENTO: BOGOTÁ D.C.\n" .
                   "FECHA EXPEDICIÓN: " . now()->subYears(2)->format('d/m/Y');
        }
        
        if (strpos($lower, 'camara') !== false || strpos($lower, 'comercio') !== false) {
            return "CÁMARA DE COMERCIO DE BOGOTÁ\n" .
                   "CERTIFICADO DE EXISTENCIA Y REPRESENTACIÓN LEGAL\n" .
                   "RAZÓN SOCIAL: EMPRESA DEMO SAS\n" .
                   "NIT: 900123456-1\n" .
                   "MATRÍCULA: 03456789\n" .
                   "FECHA CONSTITUCIÓN: 15/06/2020\n" .
                   "FECHA EXPEDICIÓN: " . now()->subDays(15)->format('d/m/Y') . "\n" .
                   "DIRECCIÓN: CALLE 123 # 45-67 BOGOTÁ D.C.\n" .
                   "REPRESENTANTE LEGAL: JUAN CARLOS PÉREZ GARCÍA\n" .
                   "DOCUMENTO: 12345678";
        }
        
        return "DOCUMENTO SIMULADO\n" .
               "Archivo: {$filename}\n" .
               "Texto extraído mediante AWS Textract (modo simulado)\n" .
               "Fecha de procesamiento: " . now()->format('d/m/Y H:i:s');
    }

    /**
     * Generate mock structured data
     */
    private function generateMockStructuredData(string $filename, string $analysisType): array
    {
        $lower = strtolower($filename);
        
        $structuredData = [
            'key_value_pairs' => [],
            'tables' => [],
            'form_fields' => []
        ];

        if (strpos($lower, 'rut') !== false) {
            $structuredData['form_fields'] = [
                'nit' => ['value' => '900123456-1', 'confidence' => 95.2],
                'razon_social' => ['value' => 'EMPRESA DEMO SAS', 'confidence' => 92.8],
                'direccion' => ['value' => 'CALLE 123 # 45-67 BOGOTÁ D.C.', 'confidence' => 89.5],
                'telefono' => ['value' => '601-234-5678', 'confidence' => 94.1]
            ];
        }

        return $structuredData;
    }
}