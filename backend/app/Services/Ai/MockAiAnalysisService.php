<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\AiAnalysisServiceContract;
use Illuminate\Support\Facades\Log;

/**
 * Implementación Mock del servicio de análisis IA.
 *
 * Se activa cuando Gemini no está configurado.
 * Genera respuestas realistas para desarrollo y testing.
 */
class MockAiAnalysisService implements AiAnalysisServiceContract
{
    public function analyzeCertificateText(string $text, array $options = []): array
    {
        Log::info('[AI:MOCK] Análisis simulado de certificado.');

        $analysisType = $options['analysis_type'] ?? 'general';

        return [
            'success' => true,
            'data'    => [
                'text'         => json_encode($this->generateMockAnalysis($text, $analysisType)),
                'confidence'   => 0.85,
                'processed_at' => now()->toIso8601String(),
                'model'        => 'mock-v1',
                'provider'     => 'MOCK',
            ],
        ];
    }

    public function generateEmailContent(string $analysisText, string $recipientName = '', string $emailType = 'notification'): array
    {
        Log::info('[AI:MOCK] Generación de email simulada.');

        return [
            'success' => true,
            'data'    => [
                'text'         => json_encode([
                    'subject' => "Notificación de certificado - {$recipientName}",
                    'body'    => "Estimado/a {$recipientName},\n\nSe ha procesado su solicitud de certificado.\n\nAtentamente,\nEquipo Certificate Manager",
                    'type'    => $emailType,
                ]),
                'confidence'   => 0.90,
                'processed_at' => now()->toIso8601String(),
                'model'        => 'mock-v1',
                'provider'     => 'MOCK',
            ],
        ];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function providerName(): string
    {
        return 'MOCK';
    }

    private function generateMockAnalysis(string $text, string $analysisType): array
    {
        return [
            'document_type'      => $analysisType,
            'extracted_data'     => [
                'nombre_completo'      => 'JUAN CARLOS PÉREZ GARCÍA',
                'documento_identidad'  => '12345678',
                'institucion'          => 'EMPRESA DEMO SAS',
                'nit'                  => '900123456-1',
            ],
            'validation'         => [
                'is_valid'             => true,
                'missing_fields'       => [],
                'warnings'             => [],
            ],
            'completeness_score' => 0.92,
            'word_count'         => str_word_count($text),
        ];
    }
}
