<?php

declare(strict_types=1);

namespace App\Services\Certificate;

use App\Contracts\CertificateIssuanceProvider;
use App\DTOs\Certificate\IssuanceRequest;
use App\Exceptions\Certificate\CertificateIssuanceException;
use App\Models\CertificateRequest;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;

/**
 * Factory que resuelve qué proveedor concreto debe atender una solicitud
 * de emisión, respetando el orden de precedencia documentado en el plan:
 *
 *   1. Override explícito en payload (requiere config `allow_payload_override`).
 *   2. Configuración por empresa (`companies.issuance_provider`).
 *   3. Feature flag global (`certificate.issuance.default_provider`).
 *   4. Fallback: 'mail' (legacy).
 *
 * Cumple Open/Closed: añadir un nuevo proveedor sólo requiere registrar
 * su clase en `config('certificate.issuance.providers')`.
 */
final class CertificateIssuanceProviderFactory
{
    public function __construct(
        private readonly Container $container,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Crea una instancia del proveedor indicado por su nombre lógico.
     */
    public function make(string $name): CertificateIssuanceProvider
    {
        $providers = (array) config('certificate.issuance.providers', []);
        $class     = $providers[$name] ?? null;

        if ($class === null || !class_exists($class)) {
            throw new CertificateIssuanceException(
                "Proveedor de emisión '{$name}' no registrado en config('certificate.issuance.providers').",
                500,
                $name,
            );
        }

        $instance = $this->container->make($class);

        if (!$instance instanceof CertificateIssuanceProvider) {
            throw new CertificateIssuanceException(
                "La clase '{$class}' no implementa CertificateIssuanceProvider.",
                500,
                $name,
            );
        }

        return $instance;
    }

    /**
     * Resuelve el proveedor que atenderá la solicitud, aplicando la
     * cascada de precedencia.
      *
     * @param bool $isSystem  true cuando la llamada viene de un job/cron interno;
     *                        en ese caso el providerHint siempre se respeta sin
     *                        necesidad del flag allow_payload_override.
     */
    public function resolveFor(IssuanceRequest $request, bool $callerIsAdmin = false, bool $isSystem = false): CertificateIssuanceProvider
    {
        // 1) Override explícito en payload:
        //    – callers HTTP admin con flag habilitado en config, O
        //    – llamadas internas de sistema ($isSystem=true).
        $allowOverride = $isSystem
            || ((bool) config('certificate.issuance.allow_payload_override', false) && $callerIsAdmin);

        if ($request->providerHint !== null && $allowOverride) {
            $provider = $this->make($request->providerHint);
            $this->logger->info('certificate.issuance.provider.selected', [
                'source' => 'payload_override',
                'name'   => $provider->name(),
                'cr_id'  => $request->certificateRequestId,
            ]);
            return $this->ensureSupports($provider, $request);
        }

        // 2) Override por empresa — leído de companies.issuance_provider si
        //    la columna existe (la migración puede no haberse corrido aún).
        $companyOverride = $this->resolveCompanyOverride($request->certificateRequestId);
        if ($companyOverride !== null) {
            try {
                $provider = $this->make($companyOverride);
                if ($provider->supports($request)) {
                    $this->logger->info('certificate.issuance.provider.selected', [
                        'source' => 'company_override',
                        'name'   => $provider->name(),
                        'cr_id'  => $request->certificateRequestId,
                    ]);
                    return $provider;
                }
                $this->logger->warning('certificate.issuance.provider.company_override_not_supported', [
                    'name'  => $provider->name(),
                    'cr_id' => $request->certificateRequestId,
                ]);
            } catch (CertificateIssuanceException $e) {
                $this->logger->warning('certificate.issuance.provider.company_override_invalid', [
                    'name'    => $companyOverride,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // 3) Feature flag global.
        $defaultName = (string) config('certificate.issuance.default_provider', 'mail');
        $provider    = $this->make($defaultName);

        if ($provider->supports($request)) {
            $this->logger->info('certificate.issuance.provider.selected', [
                'source' => 'default',
                'name'   => $provider->name(),
                'cr_id'  => $request->certificateRequestId,
            ]);
            return $provider;
        }

        // 4) Fallback duro al proveedor legacy 'mail' si el default no aplica.
        if ($defaultName !== 'mail') {
            $mail = $this->make('mail');
            if ($mail->supports($request)) {
                $this->logger->warning('certificate.issuance.provider.fallback_to_mail', [
                    'attempted' => $defaultName,
                    'cr_id'     => $request->certificateRequestId,
                ]);
                return $mail;
            }
        }

        throw new CertificateIssuanceException(
            "Ningún proveedor de emisión disponible soporta la solicitud {$request->certificateRequestId}.",
            422,
        );
    }

    /**
     * Resuelve el proveedor que actualmente gestiona la solicitud.
     * Útil para consultar estado y descargas post-emisión.
     */
    public function resolveManagerFor(int $certificateRequestId): CertificateIssuanceProvider
    {
        $providers = (array) config('certificate.issuance.providers', []);
        
        // Primero preguntamos a todos los proveedores explícitos si lo manejan
        // (por ejemplo, si tiene registro en BD de Viafirma)
        foreach ($providers as $name => $class) {
            if ($name === 'mail') continue; // Fallback al final
            
            try {
                $provider = $this->make($name);
                if ($provider->manages($certificateRequestId)) {
                    return $provider;
                }
            } catch (CertificateIssuanceException) {
                // Ignorar si un proveedor falla al instanciarse
            }
        }

        // Si nadie lo maneja de forma estricta, recae en el legacy mail
        if (isset($providers['mail'])) {
            try {
                $mail = $this->make('mail');
                if ($mail->manages($certificateRequestId)) {
                    return $mail;
                }
            } catch (CertificateIssuanceException) {
            }
        }

        throw new CertificateIssuanceException(
            "Ningún proveedor gestiona la solicitud {$certificateRequestId}.",
            404,
        );
    }

    private function ensureSupports(
        CertificateIssuanceProvider $provider,
        IssuanceRequest $request,
    ): CertificateIssuanceProvider {
        if (!$provider->supports($request)) {
            throw new CertificateIssuanceException(
                "El proveedor '{$provider->name()}' no soporta esta solicitud (prerrequisitos no cumplidos).",
                422,
                $provider->name(),
            );
        }
        return $provider;
    }

    /**
     * Lee `companies.issuance_provider` para la solicitud dada.
     * Retorna null si la solicitud no tiene empresa o el valor es null/vacío.
     */
    private function resolveCompanyOverride(int $certificateRequestId): ?string
    {
        $cr = CertificateRequest::query()
            ->with('company:id,issuance_provider')
            ->find($certificateRequestId, ['id', 'company_id']);

        $override = $cr?->company?->issuance_provider;
        return is_string($override) && $override !== '' ? $override : null;
    }
}

