<?php

namespace App\Commands\Certificate;

/**
 * Comando para cambiar el estado de una solicitud de certificado.
 */
final class UpdateCertificateStatusCommand implements CertificateCommandInterface
{
    public function __construct(
        public readonly int     $certificateId,
        public readonly int     $companyId,
        public readonly string  $requestStatus,
        public readonly ?string $comments,
        public readonly string  $userOfChange,
        public readonly int     $userId,
    ) {}
}
