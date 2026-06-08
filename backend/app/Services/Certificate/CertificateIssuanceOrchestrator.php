<?php

declare(strict_types=1);

namespace App\Services\Certificate;

use App\Contracts\CertificateIssuanceProvider;
use App\DTOs\Certificate\IssuanceRequest;
use App\DTOs\Certificate\IssuanceResult;
use App\Exceptions\Certificate\CertificateIssuanceException;
use App\Models\CertificateRequest;
use Psr\Log\LoggerInterface;

/**
 * Orquesta el flujo de emisión de un certificado, independientemente del
 * proveedor concreto.
 *
 * Responsabilidades:
 *  - Verificar que la solicitud existe.
 *  - Delegar al factory la selección del proveedor adecuado.
 *  - Centralizar logging y manejo de excepciones de negocio.
 *
 * ⚠️ IMPORTANTE — Sin DB::transaction() aquí a propósito:
 *  El proveedor Viafirma realiza llamadas HTTP externas (getProfiles, submitCsr)
 *  que pueden tardar hasta 30 s. Mantener un lockForUpdate durante esas llamadas
 *  provoca que MySQL agote el innodb_lock_wait_timeout y mate la conexión, lo que
 *  hace que el worker muera silenciosamente (sin FAILED en la cola).
 *  La atomicidad de escrituras la garantiza el DB::transaction() interno de
 *  IssueCertificateUseCase; la protección contra emisiones duplicadas la provee
 *  ViafirmaIssuanceProvider::supports() + la comprobación de registro existente
 *  dentro del propio UseCase.
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
     * Dispara la emisión desde un job/proceso interno del sistema.
     *
     * A diferencia de {@see dispatch()}, respeta el `providerHint` del request
     * sin requerir `callerIsAdmin`, ya que la llamada proviene de código de
     * sistema (watchdog, cron) y no de un usuario externo.
     */
    public function dispatchAsSystem(IssuanceRequest $request): IssuanceResult
    {
        $cr = CertificateRequest::query()->find($request->certificateRequestId);

        if ($cr === null) {
            throw new CertificateIssuanceException(
                "La solicitud de certificado {$request->certificateRequestId} no existe.",
                404,
            );
        }

        // isSystem=true → providerHint siempre se respeta sin allow_payload_override
        $provider = $this->factory->resolveFor($request, callerIsAdmin: true, isSystem: true);

        $this->logger->info('certificate.issuance.dispatching', [
            'cr_id'    => $cr->id,
            'provider' => $provider->name(),
            'user_id'  => $request->requestedByUserId,
            'source'   => 'system',
        ]);

        $result = $provider->issue($request);

        $this->logger->info('certificate.issuance.dispatched', [
            'cr_id'    => $cr->id,
            'provider' => $provider->name(),
            'status'   => $result->status,
        ]);

        return $result;
    }

    /**
     * Dispara la emisión usando el provider activo.
     */
    public function dispatch(IssuanceRequest $request, bool $callerIsAdmin = false): IssuanceResult
    {
        $cr = CertificateRequest::query()->find($request->certificateRequestId);

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
