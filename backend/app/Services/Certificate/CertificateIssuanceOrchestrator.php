<?php

declare(strict_types=1);

namespace App\Services\Certificate;

use App\Contracts\CertificateIssuanceProvider;
use App\DTOs\Certificate\IssuanceRequest;
use App\DTOs\Certificate\IssuanceResult;
use App\Exceptions\Certificate\CertificateIssuanceException;
use App\Models\CertificateRequest;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

/**
 * Orquesta el flujo de emisión de un certificado, independientemente del
 * proveedor concreto.
 *
 * Responsabilidades:
 *  - Cargar (con lock pesimista) la solicitud de negocio.
 *  - Delegar al factory la selección del proveedor adecuado.
 *  - Centralizar logging y manejo de excepciones de negocio.
 *
 * No conoce los detalles de email ni de Viafirma — sólo el contrato
 * {@see CertificateIssuanceProvider}.
 */
final class CertificateIssuanceOrchestrator
{
    public function __construct(
        private readonly CertificateIssuanceProviderFactory $factory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Dispara la emisión usando el provider activo.
     */
    public function dispatch(IssuanceRequest $request, bool $callerIsAdmin = false): IssuanceResult
    {
        return DB::transaction(function () use ($request, $callerIsAdmin) {
            $cr = CertificateRequest::query()
                ->lockForUpdate()
                ->find($request->certificateRequestId);

            if ($cr === null) {
                throw new CertificateIssuanceException(
                    "La solicitud de certificado {$request->certificateRequestId} no existe.",
                    404,
                );
            }

            $provider = $this->factory->resolveFor($request, $callerIsAdmin);

            $this->logger->info('certificate.issuance.dispatching', [
                'cr_id'    => $cr->id,
                'provider' => $provider->name(),
                'user_id'  => $request->requestedByUserId,
            ]);

            $result = $provider->issue($request);

            $this->logger->info('certificate.issuance.dispatched', [
                'cr_id'    => $cr->id,
                'provider' => $provider->name(),
                'status'   => $result->status,
            ]);

            return $result;
        });
    }

    /**
     * Consulta el estado de emisión usando el provider activo.
     * No requiere transacción ni lock (sólo lectura).
     */
    public function status(int $certificateRequestId, bool $callerIsAdmin = false): IssuanceResult
    {
        $cr = CertificateRequest::query()->find($certificateRequestId);
        if ($cr === null) {
            throw new CertificateIssuanceException(
                "La solicitud de certificado {$certificateRequestId} no existe.",
                404,
            );
        }

        $dummyRequest = new IssuanceRequest(
            certificateRequestId: $certificateRequestId,
        );
        $provider = $this->factory->resolveFor($dummyRequest, $callerIsAdmin);

        return $provider->status($certificateRequestId);
    }

    /**
     * Devuelve el proveedor activo para una solicitud (útil para descargas
     * Viafirma o cualquier operación adicional específica del proveedor).
     */
    public function providerFor(int $certificateRequestId, bool $callerIsAdmin = false): CertificateIssuanceProvider
    {
        $dummyRequest = new IssuanceRequest(
            certificateRequestId: $certificateRequestId,
        );
        return $this->factory->resolveFor($dummyRequest, $callerIsAdmin);
    }
}

