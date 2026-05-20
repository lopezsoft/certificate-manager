<?php

declare(strict_types=1);

namespace App\DTOs\Certificate;

use Illuminate\Http\Request;

/**
 * DTO inmutable que viaja desde la capa HTTP hacia el orquestador y los
 * providers de emisión.
 *
 * Es deliberadamente agnóstico al proveedor: contiene el ID de la solicitud
 * de negocio (CertificateRequest) y metadatos opcionales. Cada provider
 * resuelve internamente lo que necesite a partir de la BD.
 */
final class IssuanceRequest
{
    /**
     * @param array<string,mixed> $metadata Información adicional que el caller
     *        quiera persistir en auditoría (comentarios, hint de proveedor, etc.).
     */
    public function __construct(
        public readonly int     $certificateRequestId,
        public readonly ?int    $requestedByUserId   = null,
        public readonly ?string $emailCertificate    = null,
        public readonly ?string $organizationType    = null,
        public readonly ?string $identityTypeOverride = null,
        public readonly ?string $providerHint        = null,
        public readonly ?string $comments            = null,
        public readonly array   $metadata            = [],
    ) {}

    /**
     * Factoría desde un Request HTTP ya validado por el FormRequest.
     */
    public static function fromRequest(Request $request, int $certificateRequestId): self
    {
        return new self(
            certificateRequestId:  $certificateRequestId,
            requestedByUserId:     $request->user()?->id,
            emailCertificate:      $request->input('email_certificate'),
            organizationType:      $request->input('organization_type'),
            identityTypeOverride:  $request->input('identity_type_override'),
            providerHint:          $request->input('provider'),
            comments:              $request->input('comments'),
            metadata:              (array) $request->input('metadata', []),
        );
    }
}

