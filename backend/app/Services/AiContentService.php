<?php

namespace App\Services;

use Gemini\Client;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ModelType;
use Illuminate\Support\Facades\Log;
use Exception;

class AiContentService
{
    private $client;
    private $model;

    public function __construct()
    {
        try {
            $this->client = new Client(config('ai.gemini.api_key'));
            $this->model = config('ai.gemini.model');
        } catch (Exception $e) {
            Log::error('Error initializing Gemini client: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Analyze extracted text from certificates and extract structured data
     *
     * @param string $extractedText
     * @return array
     */
    public function analyzeCertificateText(string $extractedText): array
    {
        try {
            $prompt = $this->buildCertificateAnalysisPrompt($extractedText);
            
            $response = $this->client->generativeModel(model: $this->model)
                ->generateContent($prompt);

            $generatedText = $response->text();
            
            // Try to parse as JSON
            $structuredData = json_decode($generatedText, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // If not valid JSON, return as plain text with some basic parsing
                $structuredData = $this->fallbackParsing($generatedText);
            }

            return [
                'success' => true,
                'message' => 'Certificate text analyzed successfully',
                'data' => $structuredData,
                'raw_response' => $generatedText
            ];

        } catch (Exception $e) {
            Log::error('AI Content Service Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error analyzing certificate text: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Generate personalized email content
     *
     * @param array $certificateData
     * @param string $recipientName
     * @param string $emailType (notification, reminder, congratulations)
     * @return array
     */
    public function generateEmailContent(array $certificateData, string $recipientName, string $emailType = 'notification'): array
    {
        try {
            $prompt = $this->buildEmailPrompt($certificateData, $recipientName, $emailType);
            
            $response = $this->client->generativeModel(model: $this->model)
                ->generateContent($prompt);

            $emailContent = $response->text();
            
            // Parse the email content
            $parsedEmail = $this->parseEmailContent($emailContent);

            return [
                'success' => true,
                'message' => 'Email content generated successfully',
                'data' => $parsedEmail
            ];

        } catch (Exception $e) {
            Log::error('Email Generation Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error generating email content: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Classify document type
     *
     * @param string $extractedText
     * @return array
     */
    public function classifyDocument(string $extractedText): array
    {
        try {
            $prompt = $this->buildClassificationPrompt($extractedText);
            
            $response = $this->client->generativeModel(model: $this->model)
                ->generateContent($prompt);

            $classification = $response->text();
            
            return [
                'success' => true,
                'message' => 'Document classified successfully',
                'data' => [
                    'document_type' => trim($classification),
                    'confidence' => $this->estimateConfidence($classification)
                ]
            ];

        } catch (Exception $e) {
            Log::error('Classification Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error classifying document: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Build prompt for certificate analysis
     *
     * @param string $text
     * @return string
     */
    private function buildCertificateAnalysisPrompt(string $text): string
    {
        return "Analiza el siguiente texto extraído de un certificado y extrae la información estructurada en formato JSON. 
        Busca específicamente estos campos cuando sea posible:
        - nombre_completo: Nombre completo de la persona certificada
        - documento_identidad: Número de documento (cédula, pasaporte, etc.)
        - titulo_certificado: Título o nombre del certificado/curso
        - institucion: Institución que emite el certificado
        - fecha_emision: Fecha de emisión (formato YYYY-MM-DD si es posible)
        - fecha_expiracion: Fecha de expiración si aplica (formato YYYY-MM-DD si es posible)
        - duracion_horas: Duración en horas del curso/certificación
        - codigo_verificacion: Código de verificación si existe
        - tipo_certificado: Tipo de certificado (curso, diploma, certificación profesional, etc.)
        - nivel: Nivel académico o profesional si aplica
        - notas_adicionales: Cualquier información adicional relevante

        Si no encuentras algún campo, usa null. Responde SOLO con el JSON válido, sin texto adicional.

        Texto del certificado:
        {$text}";
    }

    /**
     * Build prompt for email generation
     *
     * @param array $certificateData
     * @param string $recipientName
     * @param string $emailType
     * @return string
     */
    private function buildEmailPrompt(array $certificateData, string $recipientName, string $emailType): string
    {
        $certificateName = $certificateData['titulo_certificado'] ?? 'certificado';
        $institution = $certificateData['institucion'] ?? 'nuestra institución';
        
        $typeInstructions = [
            'notification' => 'un correo de notificación informando que se ha registrado exitosamente',
            'reminder' => 'un correo de recordatorio sobre la próxima expiración',
            'congratulations' => 'un correo de felicitaciones por haber obtenido'
        ];

        $instruction = $typeInstructions[$emailType] ?? $typeInstructions['notification'];

        return "Genera {$instruction} el certificado '{$certificateName}' de {$institution}.

        Datos del certificado:
        " . json_encode($certificateData, JSON_PRETTY_PRINT) . "

        Destinatario: {$recipientName}

        El correo debe ser profesional, cordial y en español. Incluye:
        - Asunto del correo
        - Saludo personalizado
        - Cuerpo del mensaje con la información relevante
        - Despedida profesional

        Formato de respuesta:
        ASUNTO: [asunto del correo]
        
        CUERPO:
        [contenido del correo]";
    }

    /**
     * Build prompt for document classification
     *
     * @param string $text
     * @return string
     */
    private function buildClassificationPrompt(string $text): string
    {
        return "Clasifica el siguiente documento en una de estas categorías basándote en su contenido:
        - CERTIFICADO_CURSO: Certificado de finalización de curso
        - CERTIFICADO_PROFESIONAL: Certificación profesional o técnica
        - DIPLOMA: Diploma académico (título universitario, etc.)
        - LICENCIA: Licencia profesional o permiso
        - CONSTANCIA: Constancia de participación o asistencia
        - TITULO_ACADEMICO: Título académico formal
        - OTRO: Si no encaja en las categorías anteriores

        Responde SOLO con la categoría correspondiente, sin texto adicional.

        Texto del documento:
        {$text}";
    }

    /**
     * Parse email content from AI response
     *
     * @param string $content
     * @return array
     */
    private function parseEmailContent(string $content): array
    {
        $lines = explode("\n", $content);
        $subject = '';
        $body = '';
        $bodyStarted = false;

        foreach ($lines as $line) {
            $line = trim($line);
            
            if (stripos($line, 'ASUNTO:') === 0) {
                $subject = trim(str_ireplace('ASUNTO:', '', $line));
            } elseif (stripos($line, 'CUERPO:') === 0) {
                $bodyStarted = true;
                continue;
            } elseif ($bodyStarted) {
                $body .= $line . "\n";
            }
        }

        return [
            'subject' => $subject ?: 'Información sobre su certificado',
            'body' => trim($body) ?: $content
        ];
    }

    /**
     * Fallback parsing when JSON parsing fails
     *
     * @param string $text
     * @return array
     */
    private function fallbackParsing(string $text): array
    {
        return [
            'raw_analysis' => $text,
            'parsing_status' => 'fallback',
            'message' => 'La respuesta no pudo ser parseada como JSON, se devuelve como texto'
        ];
    }

    /**
     * Estimate confidence based on response characteristics
     *
     * @param string $response
     * @return float
     */
    private function estimateConfidence(string $response): float
    {
        // Simple heuristic: longer, more specific responses tend to be more confident
        $length = strlen(trim($response));
        
        if ($length > 50) {
            return 0.9;
        } elseif ($length > 20) {
            return 0.7;
        } elseif ($length > 10) {
            return 0.5;
        }
        
        return 0.3;
    }
}