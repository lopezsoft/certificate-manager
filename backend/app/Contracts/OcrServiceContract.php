<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contrato para servicios de OCR — Adapter Pattern.
 *
 * Permite intercambiar Google Vision, AWS Textract o cualquier
 * otro proveedor sin modificar la lógica de negocio.
 */
interface OcrServiceContract
{
    /**
     * Extrae texto de una imagen o documento.
     *
     * @return array{success: bool, data: ?array{text: string, confidence: float, blocks: int, language: string, extraction_time: float}, message?: string}
     */
    public function extractText(string $filePath): array;

    /**
     * Extrae datos estructurados de un documento (bloques, párrafos).
     *
     * @return array{success: bool, data: ?array, message?: string}
     */
    public function extractDocumentData(string $filePath): array;

    /**
     * Indica si el servicio está configurado y operativo.
     */
    public function isAvailable(): bool;

    /**
     * Nombre del proveedor (e.g. "GOOGLE_VISION", "AWS_TEXTRACT", "MOCK").
     */
    public function providerName(): string;
}
