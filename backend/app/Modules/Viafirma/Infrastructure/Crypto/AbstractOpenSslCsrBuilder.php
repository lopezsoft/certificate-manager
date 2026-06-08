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
        $this->validate($input);

        $dn = $this->dn($input);

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
}

