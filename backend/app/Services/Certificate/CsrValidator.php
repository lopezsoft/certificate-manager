<?php

declare(strict_types=1);

namespace App\Services\Certificate;

use App\Modules\Viafirma\Application\DTOs\CsrInputDto;
use Illuminate\Support\Facades\Process;

/**
 * Validador de CSR (Certificate Signing Request) PKCS#10.
 *
 * Verifica que el CSR contenga todos los atributos X.509 correctos
 * antes de enviarlo a Viafirma. Para debugging pre-producción.
 */
final class CsrValidator
{
    /**
     * Valida un CSR en formato PEM.
     *
     * @return array{valid: bool, errors: array, warnings: array, attributes: array}
     */
    public static function validate(string $csrPem): array
    {
        $errors = [];
        $warnings = [];
        $attributes = [];

        // 1) Verificar que sea un CSR válido
        if (!self::isPemFormat($csrPem)) {
            $errors[] = 'El CSR no está en formato PEM válido';
            return [
                'valid' => false,
                'errors' => $errors,
                'warnings' => $warnings,
                'attributes' => $attributes,
            ];
        }

        // 2) Parsear con OpenSSL
        $content = self::parseCsrWithOpenssl($csrPem);
        if ($content === null) {
            $errors[] = 'No se pudo parsear el CSR con OpenSSL. Verifica que sea PKCS#10 válido';
            return [
                'valid' => false,
                'errors' => $errors,
                'warnings' => $warnings,
                'attributes' => $attributes,
            ];
        }

        // 3) Extraer atributos
        $attributes = self::extractAttributes($content);

        // 4) Validar atributos requeridos
        $requiredAttrs = ['CN', 'O', 'C', 'L', 'emailAddress'];
        foreach ($requiredAttrs as $attr) {
            if (empty($attributes[$attr])) {
                $errors[] = "Atributo requerido faltante: {$attr}";
            }
        }

        // 5) Validar atributos opcionales
        if (empty($attributes['OU'])) {
            $warnings[] = "OU (Organization Unit) no presente — debería estar para FE-PJ";
        }
        if (empty($attributes['ST'])) {
            $warnings[] = "ST (State/Department) no presente";
        }

        // 6) Validar valores específicos
        if (!empty($attributes['C']) && $attributes['C'] !== 'CO') {
            $errors[] = "País debe ser CO (Colombia), encontrado: {$attributes['C']}";
        }

        if (!empty($attributes['emailAddress']) && !filter_var($attributes['emailAddress'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email inválido: {$attributes['emailAddress']}";
        }

        // 7) Verificar firma del CSR
        if (strpos($content, 'Signature ok') === false) {
            $errors[] = 'La firma del CSR no es válida';
        }

        // 8) Verificar que sea PKCS#10 (signature algorithm)
        if (strpos($content, 'Signature Algorithm:') === false) {
            $warnings[] = 'No se detectó algoritmo de firma (posible CSR incompleto)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'attributes' => $attributes,
        ];
    }

    /**
     * Parsea un CSR con OpenSSL y retorna el output.
     */
    private static function parseCsrWithOpenssl(string $csrPem): ?string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csr_');
        file_put_contents($tempFile, $csrPem);

        try {
            $result = Process::run("openssl req -in {$tempFile} -noout -text 2>&1");
            return $result->successful() ? $result->output() : null;
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Extrae los atributos X.509 del contenido parseado del CSR.
     *
     * @return array<string, string>
     */
    private static function extractAttributes(string $content): array
    {
        $attributes = [];

        // Extraer Subject (CN, O, OU, C, ST, L, emailAddress)
        preg_match('/Subject:\s*(.+?)(?:\n|$)/i', $content, $matches);
        if (!empty($matches[1])) {
            $subject = $matches[1];

            // Parsear los componentes del Subject
            if (preg_match('/CN\s*=\s*([^,]+)/', $subject, $m)) {
                $attributes['CN'] = trim($m[1]);
            }
            if (preg_match('/O\s*=\s*([^,]+)/', $subject, $m)) {
                $attributes['O'] = trim($m[1]);
            }
            if (preg_match('/OU\s*=\s*([^,]+)/', $subject, $m)) {
                $attributes['OU'] = trim($m[1]);
            }
            if (preg_match('/C\s*=\s*([^,]+)/', $subject, $m)) {
                $attributes['C'] = trim($m[1]);
            }
            if (preg_match('/ST\s*=\s*([^,]+)/', $subject, $m)) {
                $attributes['ST'] = trim($m[1]);
            }
            if (preg_match('/L\s*=\s*([^,]+)/', $subject, $m)) {
                $attributes['L'] = trim($m[1]);
            }
            if (preg_match('/emailAddress\s*=\s*([^,\n]+)/', $subject, $m)) {
                $attributes['emailAddress'] = trim($m[1]);
            }
        }

        // Extraer Subject Alternative Name si existe
        preg_match('/Subject Alternative Name:\s*(.+?)(?:\n|$)/i', $content, $matches);
        if (!empty($matches[1])) {
            $attributes['SAN'] = trim($matches[1]);
        }

        // Extraer Public Key Info
        preg_match('/Public Key:\s*\((\d+) bit ([^\)]+)\)/i', $content, $matches);
        if (!empty($matches[1])) {
            $attributes['PublicKeySize'] = $matches[1] . ' bits';
            $attributes['PublicKeyAlgorithm'] = $matches[2] ?? 'unknown';
        }

        return $attributes;
    }

    /**
     * Verifica que el string sea un CSR en formato PEM.
     */
    private static function isPemFormat(string $pem): bool
    {
        return strpos($pem, 'BEGIN CERTIFICATE REQUEST') !== false
            && strpos($pem, 'END CERTIFICATE REQUEST') !== false;
    }
}
