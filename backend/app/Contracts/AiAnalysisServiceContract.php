<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contrato para servicios de análisis IA — Adapter Pattern.
 *
 * Permite intercambiar Gemini, OpenAI, Claude o cualquier
 * otro proveedor de IA generativa sin modificar la lógica.
 */
interface AiAnalysisServiceContract
{
    /**
     * Analiza texto de un documento de certificado.
     *
     * @return array{success: bool, data: ?array{text: string, confidence: float, processed_at: string}, error?: string}
     */
    public function analyzeCertificateText(string $text, array $options = []): array;

    /**
     * Genera contenido de email basado en análisis.
     *
     * @return array{success: bool, data: ?array, error?: string}
     */
    public function generateEmailContent(string $analysisText, string $recipientName = '', string $emailType = 'notification'): array;

    /**
     * Indica si el servicio está configurado y operativo.
     */
    public function isAvailable(): bool;

    /**
     * Nombre del proveedor (e.g. "GEMINI", "OPENAI", "MOCK").
     */
    public function providerName(): string;
}
