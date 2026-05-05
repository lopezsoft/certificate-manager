<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\AiAnalysisServiceContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adapter IA para Google Gemini API.
 *
 * Se activa solo cuando GEMINI_API_KEY está configurada en .env.
 */
class GeminiAnalysisService implements AiAnalysisServiceContract
{
    private readonly string $apiKey;
    private readonly string $model;
    private readonly int $timeout;

    public function __construct()
    {
        $this->apiKey  = (string) config('ai.gemini.api_key', '');
        $this->model   = (string) config('ai.gemini.model', 'gemini-1.5-flash');
        $this->timeout = (int) config('ai.processing.timeout', 30);
    }

    public function analyzeCertificateText(string $text, array $options = []): array
    {
        $prompt = $this->buildCertificatePrompt($text, $options);
        return $this->makeRequest($prompt);
    }

    public function generateEmailContent(string $analysisText, string $recipientName = '', string $emailType = 'notification'): array
    {
        $prompt = $this->buildEmailPrompt($analysisText, $recipientName, $emailType);
        return $this->makeRequest($prompt);
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function providerName(): string
    {
        return 'GEMINI';
    }

    private function makeRequest(string $prompt): array
    {
        if (! $this->isAvailable()) {
            Log::warning('[AI:GEMINI] API Key no configurada.');
            return ['success' => false, 'data' => null, 'error' => 'Gemini no configurado.'];
        }

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'contents' => [[
                        'parts' => [['text' => $prompt]],
                    ]],
                    'generationConfig' => [
                        'temperature'    => 0.7,
                        'topK'           => 40,
                        'topP'           => 0.95,
                        'maxOutputTokens' => 1024,
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('[AI:GEMINI] Error HTTP.', ['status' => $response->status(), 'body' => $response->body()]);
                return ['success' => false, 'data' => null, 'error' => "Error HTTP {$response->status()}"];
            }

            $data         = $response->json();
            $responseText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $finishReason = $data['candidates'][0]['finishReason'] ?? 'UNKNOWN';

            return [
                'success' => true,
                'data'    => [
                    'text'         => $responseText,
                    'confidence'   => $finishReason === 'STOP' ? 0.92 : 0.70,
                    'processed_at' => now()->toIso8601String(),
                    'model'        => $this->model,
                    'provider'     => 'GEMINI',
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('[AI:GEMINI] Excepción.', ['error' => $e->getMessage()]);
            return ['success' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    private function buildCertificatePrompt(string $text, array $options): string
    {
        $analysisType = $options['analysis_type'] ?? 'general';

        $prompt = "Analiza el siguiente texto extraído de un documento de certificado:\n\nTEXTO:\n{$text}\n\n";

        $prompt .= match ($analysisType) {
            'rut'              => "Extrae: NIT, razón social, dirección, tipo persona. Verifica si está activo.\n",
            'cedula'           => "Extrae: nombres, apellidos, número de cédula, fecha nacimiento.\n",
            'chamber_commerce' => "Extrae: razón social, NIT, fecha expedición, representante legal.\n",
            default            => "Identifica tipo de documento, extrae información clave, evalúa completitud.\n",
        };

        $prompt .= "Responde en formato JSON con campos: document_type, extracted_data, validation, completeness_score.";
        return $prompt;
    }

    private function buildEmailPrompt(string $analysisText, string $recipientName, string $emailType): string
    {
        return "Genera un email profesional basado en:\n\n"
            . "ANÁLISIS: {$analysisText}\n"
            . "DESTINATARIO: {$recipientName}\n"
            . "TIPO: {$emailType}\n\n"
            . "Responde en JSON con campos: subject, body, type";
    }
}
