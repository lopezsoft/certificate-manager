<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Exception;

class AiContentService
{
    private $client;
    private $apiKey;
    private $model;

    public function __construct()
    {
        try {
            $this->client = new Client();
            $this->apiKey = config('ai.gemini.api_key');
            $this->model = config('ai.gemini.model', 'gemini-1.5-flash');
        } catch (Exception $e) {
            Log::error('Error initializing AI client: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Analyze certificate text using AI
     */
    public function analyzeCertificateText(string $text, array $options = []): array
    {
        try {
            $prompt = $this->buildCertificateAnalysisPrompt($text, $options);
            return $this->makeGeminiRequest($prompt);
        } catch (Exception $e) {
            Log::error('Error analyzing certificate text: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Generate email content based on analysis
     */
    public function generateEmailContent(string $analysisText, string $recipientName = '', string $emailType = 'notification'): array
    {
        try {
            $prompt = $this->buildEmailPrompt($analysisText, $recipientName, $emailType);
            return $this->makeGeminiRequest($prompt);
        } catch (Exception $e) {
            Log::error('Error generating email content: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Generate a simple response using AI
     */
    public function generateSimpleResponse(string $prompt): array
    {
        try {
            return $this->makeGeminiRequest($prompt);
        } catch (Exception $e) {
            Log::error('Error generating simple response: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Make a request to Google Gemini API
     */
    private function makeGeminiRequest(string $prompt): array
    {
        try {
            // For now, return a mock response since we don't have API keys
            $mockResponse = [
                'success' => true,
                'data' => [
                    'text' => 'Análisis simulado: ' . substr($prompt, 0, 100) . '...',
                    'confidence' => 0.85,
                    'processed_at' => now()->toISOString()
                ]
            ];

            // [Sprint 4 — Bloqueado] Implementación real de Gemini API pendiente de credenciales.
            /*
            $response = $this->client->post('https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 1024
                    ]
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            return [
                'success' => true,
                'data' => [
                    'text' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
                    'confidence' => $data['candidates'][0]['finishReason'] === 'STOP' ? 0.9 : 0.7,
                    'processed_at' => now()->toISOString()
                ]
            ];
            */

            return $mockResponse;

        } catch (Exception $e) {
            Log::error('Error making Gemini API request: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Build prompt for certificate analysis
     */
    private function buildCertificateAnalysisPrompt(string $text, array $options = []): string
    {
        $analysisType = $options['analysis_type'] ?? 'general';
        
        $prompt = "Analiza el siguiente texto extraído de un documento de certificado y proporciona un análisis detallado:\n\n";
        $prompt .= "TEXTO DEL DOCUMENTO:\n" . $text . "\n\n";
        
        switch ($analysisType) {
            case 'rut':
                $prompt .= "INSTRUCCIONES ESPECÍFICAS PARA RUT:\n";
                $prompt .= "- Identifica si es persona natural o jurídica\n";
                $prompt .= "- Extrae: NIT, razón social, dirección, teléfono\n";
                $prompt .= "- Verifica si está activo\n";
                break;
                
            case 'cedula':
                $prompt .= "INSTRUCCIONES ESPECÍFICAS PARA CÉDULA:\n";
                $prompt .= "- Extrae: nombres, apellidos, número de cédula\n";
                $prompt .= "- Verifica que esté completa la información\n";
                break;
                
            case 'chamber_commerce':
                $prompt .= "INSTRUCCIONES ESPECÍFICAS PARA CÁMARA DE COMERCIO:\n";
                $prompt .= "- Extrae: razón social, NIT, fecha de expedición\n";
                $prompt .= "- Verifica si la fecha de expedición es reciente (máximo 30 días)\n";
                break;
                
            default:
                $prompt .= "ANÁLISIS GENERAL:\n";
                $prompt .= "- Identifica el tipo de documento\n";
                $prompt .= "- Extrae información clave\n";
                $prompt .= "- Evalúa la completitud de los datos\n";
        }
        
        $prompt .= "\nResponde en formato JSON con la estructura apropiada para el tipo de análisis.";
        
        return $prompt;
    }

    /**
     * Build prompt for email generation
     */
    private function buildEmailPrompt(string $analysisText, string $recipientName, string $emailType): string
    {
        $prompt = "Genera un email profesional basado en el siguiente análisis de certificado:\n\n";
        $prompt .= "ANÁLISIS: " . $analysisText . "\n\n";
        $prompt .= "DESTINATARIO: " . $recipientName . "\n";
        $prompt .= "TIPO DE EMAIL: " . $emailType . "\n\n";
        
        $prompt .= "Genera un email con:\n";
        $prompt .= "- Asunto apropiado\n";
        $prompt .= "- Saludo personalizado\n";
        $prompt .= "- Cuerpo del mensaje explicando el estado del certificado\n";
        $prompt .= "- Despedida profesional\n\n";
        
        $prompt .= "Responde en formato JSON con campos: subject, body, type";
        
        return $prompt;
    }
}