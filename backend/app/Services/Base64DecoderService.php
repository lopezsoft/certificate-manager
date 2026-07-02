<?php

declare(strict_types=1);

namespace App\Services;

use Exception;

/**
 * Servicio para decodificar archivos en Base64 con soporte para prefijo Data URI.
 *
 * Maneja tanto archivos con prefijo `data:application/pdf;base64,JVBERi0xLjQK...`
 * como sin prefijo `JVBERi0xLjQK...`
 *
 * Extrae automáticamente el MIME type del prefijo si está disponible.
 */
class Base64DecoderService
{
    /**
     * Decodifica un string Base64, removiendo el prefijo Data URI si existe.
     *
     * @param string $base64String El contenido en Base64 (con o sin prefijo data:)
     * @return string El contenido binario decodificado
     * @throws Exception Si el Base64 es inválido
     */
    public function decode(string $base64String): string
    {
        // Remover prefijo Data URI si existe
        $cleanBase64 = $this->extractBase64Content($base64String);

        // Decodificar
        $binaryContent = @base64_decode($cleanBase64, true);

        if ($binaryContent === false) {
            throw new Exception('El contenido en base64 no es válido', 400);
        }

        return $binaryContent;
    }

    /**
     * Extrae el MIME type del prefijo Data URI.
     *
     * Ejemplo: `data:application/pdf;base64,JVBERi0xLjQK...` → `application/pdf`
     *
     * @param string $base64String El contenido en Base64 (con o sin prefijo data:)
     * @return string|null El MIME type extraído, o null si no hay prefijo
     */
    public function extractMimeType(string $base64String): ?string
    {
        // Patrón: data:MIME_TYPE;base64,
        if (preg_match('/^data:([a-zA-Z0-9\-+\/]+);base64,/', $base64String, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extrae el contenido Base64 limpio, removiendo el prefijo Data URI si existe.
     *
     * Ejemplo: `data:application/pdf;base64,JVBERi0xLjQK...` → `JVBERi0xLjQK...`
     *
     * @param string $base64String El contenido en Base64 (con o sin prefijo data:)
     * @return string El contenido Base64 limpio sin prefijo
     */
    public function extractBase64Content(string $base64String): string
    {
        // Si tiene prefijo data:, removerlo
        if (preg_match('/^data:[a-zA-Z0-9\-+\/]+;base64,(.+)$/', $base64String, $matches)) {
            return $matches[1];
        }

        // Si no tiene prefijo, retornar tal cual
        return $base64String;
    }

    /**
     * Valida si un string es Base64 válido (con o sin prefijo Data URI).
     *
     * @param string $base64String El contenido a validar
     * @return bool True si es Base64 válido, false en caso contrario
     */
    public function isValid(string $base64String): bool
    {
        $cleanBase64 = $this->extractBase64Content($base64String);
        return base64_decode($cleanBase64, true) !== false;
    }

    /**
     * Decodifica y retorna información completa del archivo.
     *
     * Útil para procesar archivos que vienen con prefijo Data URI.
     *
     * @param string $base64String El contenido en Base64 (con o sin prefijo data:)
     * @return array Array con contenido binario, MIME type y tamaño
     * @throws Exception Si el Base64 es inválido
     */
    public function decodeWithMetadata(string $base64String): array
    {
        $binaryContent = $this->decode($base64String);
        $mimeType = $this->extractMimeType($base64String);

        return [
            'binary_content' => $binaryContent,
            'mime_type'      => $mimeType,
            'size'           => strlen($binaryContent),
        ];
    }
}
