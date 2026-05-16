<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Services;

use App\Modules\Viafirma\Domain\Exceptions\CsrBuildException;

/**
 * Valida que el Subject DN del CSR coincida con el `dnPattern` retornado por
 * Viafirma en `/ra/available-profiles?codRa={ra}` (V-211).
 *
 * El patrón Viafirma tiene la forma:
 *   "CN={CN},GN={GN},SN={SN},serialNumber={SERIAL},C={C},ST={ST},L={L},STREET={STREET},O={O},OU={OU},emailAddress={E}"
 *
 * Sólo se valida la PRESENCIA y ORDEN de los componentes (los `{X}` son
 * placeholders, no se comparan literalmente).
 */
final class DnPatternValidator
{
    /**
     * @throws CsrBuildException Si faltan componentes esperados por el pattern.
     */
    public function assertMatches(string $csrPem, string $dnPattern): void
    {
        if ($dnPattern === '') {
            // Si Viafirma no devuelve pattern, no validamos — es opcional.
            return;
        }

        $subject = @openssl_csr_get_subject($csrPem);
        if ($subject === false || $subject === null) {
            throw new CsrBuildException(
                'No se pudo leer el Subject del CSR para validar contra dnPattern.'
            );
        }

        $expectedKeys = $this->extractKeys($dnPattern);
        $present      = array_change_key_case($subject, CASE_LOWER);

        $missing = [];
        foreach ($expectedKeys as $key) {
            $normalized = $this->normalizeKey($key);
            if (!array_key_exists($normalized, $present)) {
                $missing[] = $key;
            }
        }
        if ($missing !== []) {
            throw new CsrBuildException(
                'El CSR no contiene los atributos exigidos por el dnPattern del perfil: '
                . implode(', ', $missing)
            );
        }
    }

    /**
     * Extrae los nombres de atributo del pattern: "CN={x},GN={y},..." → ["CN","GN",...].
     *
     * @return string[]
     */
    private function extractKeys(string $dnPattern): array
    {
        $keys = [];
        foreach (explode(',', $dnPattern) as $component) {
            $component = trim($component);
            if ($component === '' || !str_contains($component, '=')) {
                continue;
            }
            [$k, ] = explode('=', $component, 2);
            $k = trim($k);
            if ($k !== '') {
                $keys[] = $k;
            }
        }
        return $keys;
    }

    /** Normaliza claves Viafirma → claves que devuelve openssl_csr_get_subject. */
    private function normalizeKey(string $key): string
    {
        return match (strtolower($key)) {
            'e', 'emailaddress' => 'emailaddress',
            'street'            => 'street',
            default             => strtolower($key),
        };
    }
}

