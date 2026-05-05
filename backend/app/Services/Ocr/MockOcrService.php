<?php

declare(strict_types=1);

namespace App\Services\Ocr;

use App\Contracts\OcrServiceContract;
use Illuminate\Support\Facades\Log;

/**
 * Implementación Mock del servicio OCR.
 *
 * Se activa automáticamente cuando Google Vision no está configurado.
 * Genera resultados realistas para desarrollo y testing.
 */
class MockOcrService implements OcrServiceContract
{
    public function extractText(string $filePath): array
    {
        $startTime = microtime(true);
        $filename  = basename($filePath);

        if (! file_exists($filePath)) {
            return ['success' => false, 'data' => null, 'message' => "Archivo no encontrado: {$filePath}"];
        }

        Log::info('[OCR:MOCK] Extracción simulada.', ['file' => $filename]);

        return [
            'success' => true,
            'data'    => [
                'text'            => $this->generateMockText($filename),
                'confidence'      => 0.87,
                'blocks'          => 3,
                'language'        => 'es',
                'extraction_time' => round(microtime(true) - $startTime, 3),
            ],
        ];
    }

    public function extractDocumentData(string $filePath): array
    {
        $result = $this->extractText($filePath);
        if (! $result['success']) {
            return $result;
        }

        $text = $result['data']['text'];
        return [
            'success' => true,
            'data'    => [
                'full_text'  => $text,
                'pages'      => 1,
                'word_count' => str_word_count($text),
                'language'   => 'es',
            ],
        ];
    }

    public function isAvailable(): bool
    {
        return true; // Mock siempre disponible
    }

    public function providerName(): string
    {
        return 'MOCK';
    }

    private function generateMockText(string $filename): string
    {
        $lower = strtolower($filename);

        if (str_contains($lower, 'rut')) {
            return "REGISTRO ÚNICO TRIBUTARIO - RUT\nNIT: 900123456-1\nRAZÓN SOCIAL: EMPRESA DEMO SAS\nDIRECCIÓN: CALLE 123 # 45-67 BOGOTÁ\nESTADO: ACTIVO\nPERSONA JURÍDICA";
        }

        if (str_contains($lower, 'cedula') || str_contains($lower, 'cc')) {
            return "REPÚBLICA DE COLOMBIA\nCÉDULA DE CIUDADANÍA\nNOMBRES: JUAN CARLOS\nAPELLIDOS: PÉREZ GARCÍA\nNÚMERO: 12345678\nFECHA NACIMIENTO: 15/03/1985";
        }

        if (str_contains($lower, 'camara') || str_contains($lower, 'comercio')) {
            return "CÁMARA DE COMERCIO DE BOGOTÁ\nCERTIFICADO DE EXISTENCIA Y REPRESENTACIÓN LEGAL\nRAZÓN SOCIAL: EMPRESA DEMO SAS\nNIT: 900123456-1\nREPRESENTANTE LEGAL: JUAN CARLOS PÉREZ GARCÍA";
        }

        return "DOCUMENTO EJEMPLO\nTexto simulado extraído de: {$filename}\nFecha: " . now()->format('d/m/Y');
    }
}
