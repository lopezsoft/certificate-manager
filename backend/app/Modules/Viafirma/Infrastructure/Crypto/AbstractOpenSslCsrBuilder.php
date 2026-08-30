<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Crypto;

use App\Modules\Viafirma\Application\DTOs\CsrInputDto;
use App\Modules\Viafirma\Application\DTOs\CsrResult;
use App\Modules\Viafirma\Domain\Contracts\CsrBuilderStrategy;
use App\Modules\Viafirma\Domain\Exceptions\CsrBuildException;

/**
 * Base común para ambos builders (FE-PJ / FE-PN).
 *
 * Encapsula:
 *  - Validación común de campos obligatorios.
 *  - Llamada a `openssl_csr_new` con los DN normalizados.
 *  - Exportación PEM + cálculo base64 + fingerprint SHA-256.
 *
 * Las subclases definen `dn(CsrInputDto)` con la forma específica del perfil.
 */
abstract class AbstractOpenSslCsrBuilder implements CsrBuilderStrategy
{
    public function __construct(
        protected readonly string $digestAlg = 'sha256',
        protected readonly ?string $opensslConf = null,
    ) {}

    /**
     * @return array<string,string> Subject DN con claves admitidas por OpenSSL.
     */
    abstract protected function dn(CsrInputDto $input): array;

    /**
     * Validaciones específicas del perfil. Lanzar {@see CsrBuildException} si fallan.
     */
    abstract protected function validate(CsrInputDto $input): void;

    /**
     * Cantidad de atributos esperados en el DN — invariante por perfil.
     */
    abstract protected function expectedAttributeCount(): int;

    public function build(CsrInputDto $input, string $privateKeyPem): CsrResult
    {
        // Validación de país centralizada — se ejecuta ANTES del validate() del perfil
        // para que AMBOS builders (FE-PJ y FE-PN) la reciban sin duplicar código.
        $this->assertValidCountryAlpha2($input->country);

        $this->validate($input);

        // Obtenemos el DN desde la subclase (FE-PJ o FE-PN)
        $dn = $this->dn($input);

        // --- INICIO DEL PARCHE: Truncar estrictamente para ASN.1 / OpenSSL ---
        // OpenSSL falla con 'asn1 encoding routines::string too long' si estos campos superan los 64 caracteres.
        // Se trunca con mb_substr para soportar caracteres UTF-8 correctamente sin corromper la cadena.
        //
        // IMPORTANTE: las claves deben coincidir EXACTAMENTE con las que usan
        // FePjCsrBuilder::dn()/FePnCsrBuilder::dn() ('CN', 'O', 'OU' — forma
        // corta que espera openssl_csr_new()). Antes de este fix el mapa usaba
        // los nombres largos ('commonName', 'organizationName', ...), que
        // nunca existían en $dn — el truncado nunca se ejecutaba y nombres de
        // empresa largos (ej. "JUNTA ADMINISTRADORA DEL ACUEDUCTO Y
        // ALCANTARILLADO DE LA VEREDA SAN FRANCISCO") rompían openssl_csr_new
        // con "asn1 encoding routines::string too long".
        $strictLimits = [
            'CN' => 64, // Límite estricto ASN.1
            'O'  => 64, // Límite estricto ASN.1
            'OU' => 64, // Límite estricto ASN.1
        ];

        foreach ($strictLimits as $field => $maxLength) {
            if (isset($dn[$field])) {
                $dn[$field] = mb_substr($dn[$field], 0, $maxLength, 'UTF-8');
            }
        }
        // --- FIN DEL PARCHE ---

        if (count($dn) !== $this->expectedAttributeCount()) {
            throw new CsrBuildException(sprintf(
                'El DN construido tiene %d atributos; el perfil exige %d.',
                count($dn),
                $this->expectedAttributeCount()
            ));
        }

        $privateKey = @openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new CsrBuildException(
                'No se pudo cargar la llave privada PEM: ' . $this->lastOpenSslError()
            );
        }

        $opensslOpts = [
            'digest_alg'       => $this->digestAlg,
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        if ($this->opensslConf !== null && is_file($this->opensslConf)) {
            $opensslOpts['config'] = $this->opensslConf;
        }

        // Aquí ya llega protegido contra desbordamientos
        $csr = @openssl_csr_new($dn, $privateKey, $opensslOpts);
        if ($csr === false) {
            throw new CsrBuildException(
                'openssl_csr_new falló: ' . $this->lastOpenSslError()
            );
        }

        $pem = '';
        if (!openssl_csr_export($csr, $pem)) {
            throw new CsrBuildException(
                'openssl_csr_export falló: ' . $this->lastOpenSslError()
            );
        }

        // Drenar la cola de errores OpenSSL (warnings no-fatales del def_load).
        // @phpcs:ignore
        while (($openSslErr = openssl_error_string()) !== false) {
            unset($openSslErr); // drain silencioso
        }

        return new CsrResult(
            pem: $pem,
            base64: $this->pemToBase64Body($pem),
            fingerprint: hash('sha256', $pem),
        );
    }

    /**
     * Codifica el PEM completo (con cabeceras BEGIN/END y saltos de línea)
     * en base64 estándar — tal como lo requiere Viafirma en el campo `csr`.
     *
     * ⚠️  Viafirma espera base64 del PEM COMPLETO (incluyendo headers y newlines),
     * equivalente a: Buffer.from(csrPem).toString('base64') en Node.js / Postman.
     * NO se debe enviar solo el body interno del PEM.
     */
    protected function pemToBase64Body(string $pem): string
    {
        return base64_encode($pem);
    }

    protected function lastOpenSslError(): string
    {
        $errors = [];
        while (($e = openssl_error_string()) !== false) {
            $errors[] = $e;
        }
        return $errors === [] ? '(sin detalle)' : implode(' | ', $errors);
    }

    protected function assertNotBlank(?string $value, string $field): void
    {
        if ($value === null || trim($value) === '') {
            throw new CsrBuildException("El campo CSR '{$field}' es obligatorio para este perfil.");
        }
    }

    /**
     * Valida que el código de país sea ISO 3166-1 alpha-2 (exactamente 2 letras).
     *
     * Si se recibe un alpha-3 conocido (ej. 'COL', 'USA') lanza excepción con
     * sugerencia del alpha-2 correcto, facilitando la corrección en el origen.
     * Centralizado aquí para que FE-PJ y FE-PN lo hereden sin duplicar.
     *
     * @throws CsrBuildException
     */
    protected function assertValidCountryAlpha2(string $country): void
    {
        $code = strtoupper(trim($country));

        if (strlen($code) === 2) {
            return; // Formato correcto
        }

        // Mapa de alpha-3 más comunes → alpha-2, para dar sugerencia en el error
        $alpha3Map = [
            'COL' => 'CO', 'USA' => 'US', 'MEX' => 'MX', 'ESP' => 'ES',
            'ARG' => 'AR', 'CHL' => 'CL', 'PER' => 'PE', 'ECU' => 'EC',
            'VEN' => 'VE', 'BOL' => 'BO', 'PRY' => 'PY', 'URY' => 'UY',
            'BRA' => 'BR', 'PAN' => 'PA', 'CRI' => 'CR', 'GTM' => 'GT',
        ];

        $suggestion = isset($alpha3Map[$code])
            ? " ¿Quiso decir '{$alpha3Map[$code]}'? (ISO 3166-1 alpha-2)"
            : '';

        throw new CsrBuildException(
            "El campo 'C' (país) debe ser ISO 3166-1 alpha-2 (2 letras); "
            . "recibido: '{$country}'.{$suggestion}"
        );
    }
}

