<?php

declare(strict_types=1);

namespace App\Services\Certificate\Providers;

use App\Contracts\CertificateIssuanceProvider;
use App\DTOs\Certificate\IssuanceRequest;
use App\DTOs\Certificate\IssuanceResult;
use App\Exceptions\Certificate\CertificateIssuanceException;
use App\Models\CertificateRequest;
use App\Services\CertificateRequestMailService;
use Illuminate\Http\Request as HttpRequest;
use Psr\Log\LoggerInterface;

/**
 * Proveedor legacy: envía la solicitud a la Autoridad Certificadora vía correo
 * electrónico (flujo actual del proyecto antes de Viafirma).
 *
 * Es un adapter delgado sobre {@see CertificateRequestMailService}. Mantiene
 * compatibilidad 100% con el endpoint deprecado `/send-mail`.
 */
final class MailIssuanceProvider implements CertificateIssuanceProvider
{
    public const NAME = 'mail';

    public function __construct(
        private readonly CertificateRequestMailService $mailService,
        private readonly LoggerInterface $logger,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(IssuanceRequest $request): bool
    {
        // El proveedor email no tiene pre-condiciones técnicas (mientras exista
        // la solicitud y tenga archivos adjuntos, puede enviarse).
        $cr = CertificateRequest::query()
            ->with('files')
            ->find($request->certificateRequestId);

        if ($cr === null) {
            return false;
        }

        return $cr->files->where('document_type', 'ATTACHED')->isNotEmpty();
    }

    public function manages(int $certificateRequestId): bool
    {
        // Proveedor fallback por defecto: si la solicitud no está en Viafirma,
        // asumimos que pertenece al flujo de correo legacy.
        return true;
    }

    public function issue(IssuanceRequest $request): IssuanceResult
    {
        // Construimos un HttpRequest sintético porque el servicio legacy lo exige.
        $synthetic = new HttpRequest();
        $synthetic->merge([
            'comments' => $request->comments,
        ]);

        $response = $this->mailService->sendMail($synthetic, $request->certificateRequestId);
        $payload  = json_decode((string) $response->getContent(), true) ?: [];

        $status = $response->getStatusCode();
        if ($status >= 400) {
            $this->logger->warning('certificate.issuance.mail.failure', [
                'cr_id'  => $request->certificateRequestId,
                'status' => $status,
            ]);
            throw new CertificateIssuanceException(
                message:     $payload['message'] ?? 'No fue posible enviar la solicitud por correo.',
                httpStatus:  $status,
                providerName: self::NAME,
            );
        }

        return new IssuanceResult(
            providerName: self::NAME,
            status:       IssuanceResult::STATUS_SENT,
            message:      $payload['message'] ?? 'Solicitud enviada por correo exitosamente.',
            externalId:   null,
            resourceId:   $request->certificateRequestId,
            httpStatus:   $status,
            data:         $payload['dataRecords'] ?? [],
        );
    }

    public function status(int $certificateRequestId): IssuanceResult
    {
        $cr = CertificateRequest::query()->find($certificateRequestId);

        if ($cr === null) {
            return new IssuanceResult(
                providerName: self::NAME,
                status:       IssuanceResult::STATUS_FAILED,
                message:      'Solicitud no encontrada.',
                resourceId:   $certificateRequestId,
                httpStatus:   404,
            );
        }

        return new IssuanceResult(
            providerName: self::NAME,
            status:       IssuanceResult::STATUS_PROCESSING,
            message:      'El proveedor de correo no expone polling. Estado tomado del CertificateRequest.',
            resourceId:   $cr->id,
            data:         [
                'request_status' => $cr->request_status ?? null,
            ],
        );
    }
}

