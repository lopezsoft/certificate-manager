<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\DTOs;

use App\Modules\Viafirma\Domain\Enums\IdentityType;
use App\Modules\Viafirma\Domain\Enums\OrganizationType;

/**
 * Input para `POST /request/fromCSR`.
 *
 * Es el payload TIPADO que viaja desde el UseCase al ViafirmaClient.
 * El cliente HTTP serializa esto al formato esperado por Viafirma,
 * omitiendo `organizationType` cuando es null (FE-PN).
 */
final class SubmitCsrInputDto
{
    public function __construct(
        public readonly IdentityType $identityType,
        public readonly string $countryCode,
        public readonly string $identity,
        public readonly string $raCode,
        public readonly string $codProfile,
        public readonly string $emailCertificate,
        /** CSR codificado base64 estándar (no URL-safe). */
        public readonly string $csrBase64,
        /** Sólo se envía si está presente (FE-PJ). */
        public readonly ?OrganizationType $organizationType = null,
    ) {}

    /**
     * Serialización tal cual la espera el endpoint de Viafirma.
     *
     * @return array<string,string>
     */
    public function toViafirmaPayload(): array
    {
        $payload = [
            'identityType'     => $this->identityType->value,
            'countryCode'      => $this->countryCode,
            'identity'         => $this->identity,
            'ra'               => $this->raCode,
            'codProfile'       => $this->codProfile,
            'emailCertificate' => $this->emailCertificate,
            'csr'              => $this->csrBase64,
        ];
        if ($this->organizationType !== null) {
            $payload['organizationType'] = $this->organizationType->value;
        }
        return $payload;
    }
}

