<?php

namespace App\Commands\Certificate;

/**
 * Comando para eliminar una solicitud de certificado.
 */
final class DeleteCertificateRequestCommand implements CertificateCommandInterface
{
    public function __construct(
        public readonly int $certificateId,
        public readonly int $companyId,
    ) {}
}
