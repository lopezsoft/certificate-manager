<?php

namespace App\Commands\Certificate;

/**
 * Comando para actualizar los datos de una solicitud de certificado existente.
 */
final class UpdateCertificateRequestCommand implements CertificateCommandInterface
{
    public function __construct(
        public readonly int     $certificateId,
        public readonly int     $companyId,
        public readonly int     $cityId,
        public readonly int     $identityDocumentId,
        public readonly int     $typeOrganizationId,
        public readonly string  $documentNumber,
        public readonly string  $address,
        public readonly ?string $legalRepresentative,
        public readonly ?string $legalRepFirstName,
        public readonly ?string $legalRepLastName,
        public readonly ?string $legalRepEmail,
        public readonly string  $companyName,
        public readonly string  $dni,
        public readonly int     $life,
        public readonly ?string $info,
        public readonly ?string $phone,
        public readonly ?string $mobile,
    ) {}
}
