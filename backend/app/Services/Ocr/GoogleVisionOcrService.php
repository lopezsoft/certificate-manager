<?php

declare(strict_types=1);

namespace App\Services\Ocr;

use App\Contracts\OcrServiceContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adapter OCR para Google Cloud Vision API.
 *
 * Se activa solo cuando las credenciales están configuradas en .env.
 * Si no están disponibles, el ServiceProvider inyecta MockOcrService.
 */
class GoogleVisionOcrService implements OcrServiceContract
{
    private readonly string $apiKey;
    private readonly string $projectId;
    private readonly int $timeout;

    public function __construct()
    {
        $this->apiKey    = (string) config('ai.google_vision.api_key', '');
        $this->projectId = (string) config('ai.google_vision.project_id', '');
        $this->timeout   = (int) config('ai.processing.timeout', 30);
    }

    public function extractText(string $filePath): array
    {
        if (! $this->isAvailable()) {
            Log::warning('[OCR:GOOGLE_VISION] Credenciales no configuradas.');
            return ['success' => false, 'data' => null, 'message' => 'Google Vision no configurado.'];
        }

        $startTime = microtime(true);

        try {
            if (! file_exists($filePath)) {
                return ['success' => false, 'data' => null, 'message' => "Archivo no encontrado: {$filePath}"];
            }

            $imageContent = base64_encode(file_get_contents($filePath));

            $response = Http::timeout($this->timeout)
                ->post("https://vision.googleapis.com/v1/images:annotate?key={$this->apiKey}", [
                    'requests' => [[
                        'image'    => ['content' => $imageContent],
                        'features' => [['type' => 'TEXT_DETECTION', 'maxResults' => 1]],
                    ]],
                ]);

            if (! $response->successful()) {
                Log::error('[OCR:GOOGLE_VISION] Error HTTP.', ['status' => $response->status()]);
                return ['success' => false, 'data' => null, 'message' => "Error HTTP {$response->status()}"];
            }

            $data = $response->json();
            $annotations = $data['responses'][0]['textAnnotations'] ?? [];

            if (empty($annotations)) {
                return ['success' => false, 'data' => null, 'message' => 'No se encontró texto en la imagen.'];
            }

            $fullText = $annotations[0]['description'] ?? '';

            return [
                'success' => true,
                'data'    => [
                    'text'            => $fullText,
                    'confidence'      => $this->calculateConfidence($annotations),
                    'blocks'          => count($annotations) - 1,
                    'language'        => $data['responses'][0]['textAnnotations'][0]['locale'] ?? 'unknown',
                    'extraction_time' => round(microtime(true) - $startTime, 3),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('[OCR:GOOGLE_VISION] Excepción.', ['error' => $e->getMessage()]);
            return ['success' => false, 'data' => null, 'message' => $e->getMessage()];
        }
    }

    public function extractDocumentData(string $filePath): array
    {
        if (! $this->isAvailable()) {
            return ['success' => false, 'data' => null, 'message' => 'Google Vision no configurado.'];
        }

        try {
            $imageContent = base64_encode(file_get_contents($filePath));

            $response = Http::timeout($this->timeout)
                ->post("https://vision.googleapis.com/v1/images:annotate?key={$this->apiKey}", [
                    'requests' => [[
                        'image'    => ['content' => $imageContent],
                        'features' => [['type' => 'DOCUMENT_TEXT_DETECTION', 'maxResults' => 1]],
                    ]],
                ]);

            if (! $response->successful()) {
                return ['success' => false, 'data' => null, 'message' => "Error HTTP {$response->status()}"];
            }

            $data     = $response->json();
            $fullText = $data['responses'][0]['fullTextAnnotation']['text'] ?? '';
            $pages    = $data['responses'][0]['fullTextAnnotation']['pages'] ?? [];

            return [
                'success' => true,
                'data'    => [
                    'full_text' => $fullText,
                    'pages'     => count($pages),
                    'word_count' => str_word_count($fullText),
                    'language'  => $this->detectLanguage($fullText),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('[OCR:GOOGLE_VISION] Excepción en extractDocumentData.', ['error' => $e->getMessage()]);
            return ['success' => false, 'data' => null, 'message' => $e->getMessage()];
        }
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey) && ! empty($this->projectId);
    }

    public function providerName(): string
    {
        return 'GOOGLE_VISION';
    }

    private function calculateConfidence(array $annotations): float
    {
        $total = 0;
        $count = 0;
        for ($i = 1, $len = count($annotations); $i < $len; $i++) {
            if (isset($annotations[$i]['confidence'])) {
                $total += $annotations[$i]['confidence'];
                $count++;
            }
        }
        return $count > 0 ? round($total / $count, 2) : 0.85;
    }

    private function detectLanguage(string $text): string
    {
        $words = str_word_count(strtolower($text), 1);
        $es = count(array_intersect($words, ['de', 'la', 'el', 'en', 'que', 'certificado']));
        $en = count(array_intersect($words, ['the', 'and', 'of', 'to', 'certificate']));
        return $es > $en ? 'es' : ($en > $es ? 'en' : 'unknown');
    }
}
