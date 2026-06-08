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
     * Extrae los nombres de atributo del pattern que contienen placeholder `{...}`.
     *
     * Los componentes con valor literal (ej. `C=CO` o `DN=EMISOR FACTURA...`)
     * se OMITEN porque no corresponden a datos del CSR; son rellenados por el RA.
     *
     * "CN={x},name={y},C=CO,DN=EMISOR..." → ["CN","name",...]
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
            [$k, $v] = explode('=', $component, 2);
            $k = trim($k);
            $v = trim($v);
            // Sólo validar campos con placeholder {xxx}; los literales los pone el RA.
            if ($k !== '' && str_contains($v, '{')) {
                $keys[] = $k;
            }
        }
        return $keys;
    }

    /**
     * Normaliza claves Viafirma → claves que devuelve openssl_csr_get_subject.
     *
     * Mapeos necesarios porque Viafirma usa los nombres largos de OID en su
     * dnPattern mientras que PHP/OpenSSL devuelve los nombres cortos:
     *   givenName / name (OID 2.5.4.42 / 2.5.4.41) → GN (PHP short name)
     *   surname          (OID 2.5.4.4)              → SN (PHP short name)
     */
    private function normalizeKey(string $key): string
    {
        return match (strtolower($key)) {
            'e', 'emailaddress'        => 'emailaddress',
            'street'                   => 'street',
            'gn', 'givenname', 'name'  => 'gn',   // givenName (long) = GN (short)
            'sn', 'surname'            => 'sn',   // surname   (long) = SN (short)
            default                    => strtolower($key),
        };
    }
}

