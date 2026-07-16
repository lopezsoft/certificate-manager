<?php

declare(strict_types=1);

namespace App\Jobs\Certificate;

use App\Commands\Certificate\UpdateCertificateStatusCommand;
use App\Enums\CertificateRequestStatusEnum;
use App\Handlers\Certificate\UpdateCertificateStatusHandler;
use App\Models\CertificateRequest;
use App\Models\User;
use App\Services\CertificateRequestMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Promoción automática de solicitudes del flujo "mail" que ya no requieren
 * intervención humana: SENT -> ACCEPTED -> PROCESSING.
 *
 * Solo aplica a empresas cuyo proveedor de emisión resuelto es 'mail'
 * (companies.issuance_provider = 'mail' explícito). NO incluye NULL:
 * en este entorno el default global (CERTIFICATE_ISSUANCE_PROVIDER) es
 * 'viafirma', por lo que una empresa con issuance_provider NULL en
 * realidad usa Viafirma.
 *
 * Encadena ambos pasos en la misma ejecución para una solicitud SENT:
 * SENT -> ACCEPTED -> PROCESSING, sin esperar al siguiente ciclo de 5 min.
 */
class PromoteMailCertificateRequestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    private const MAIL_PROVIDER = 'mail';

    public function handle(
        UpdateCertificateStatusHandler $statusHandler,
        CertificateRequestMailService $mailService
    ): void {
        try {
            $candidates = CertificateRequest::query()
                ->select('certificate_requests.*')
                ->join('companies', 'companies.id', '=', 'certificate_requests.company_id')
                ->whereIn('certificate_requests.request_status', [
                    CertificateRequestStatusEnum::SENT->value,
                    CertificateRequestStatusEnum::ACCEPTED->value,
                ])
                ->where('companies.issuance_provider', '=', self::MAIL_PROVIDER)
                ->get();

            if ($candidates->isEmpty()) {
                Log::info('certificate.auto_promotion.no_candidates');
                return;
            }

            $promoted = 0;

            foreach ($candidates as $certificateRequest) {
                try {
                    $this->promote($statusHandler, $mailService, $certificateRequest);
                    $promoted++;
                } catch (\Throwable $e) {
                    Log::error('certificate.auto_promotion.failed', [
                        'certificate_request_id' => $certificateRequest->id,
                        'from_status' => $certificateRequest->request_status,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('certificate.auto_promotion.complete', [
                'candidates' => $candidates->count(),
                'promoted' => $promoted,
            ]);
        } catch (\Throwable $e) {
            Log::error('certificate.auto_promotion.error', ['message' => $e->getMessage()]);
        }
    }

    private function promote(
        UpdateCertificateStatusHandler $statusHandler,
        CertificateRequestMailService $mailService,
        CertificateRequest $certificateRequest
    ): void {
        $adminUser = User::where('type_id', '1')->first();
        if (!$adminUser) {
            throw new \Exception('No se encontró usuario ADMIN para ejecutar la promoción automática');
        }

        Auth::login($adminUser);

        $status = $certificateRequest->request_status;

        // SENT → ACCEPTED: usar handler de cambio de estado
        if ($status === CertificateRequestStatusEnum::SENT->value) {
            $statusHandler->handle(new UpdateCertificateStatusCommand(
                certificateId: $certificateRequest->id,
                companyId: $certificateRequest->company_id,
                requestStatus: CertificateRequestStatusEnum::ACCEPTED->value,
                comments: 'La solicitud ha sido aceptada para ser procesada.',
                userOfChange: 'MANAGER',
                userId: $adminUser->id,
            ));
            $status = CertificateRequestStatusEnum::ACCEPTED->value;
        }

        // ACCEPTED → PROCESSING: usar servicio de mail directamente
        if ($status === CertificateRequestStatusEnum::ACCEPTED->value) {
            // Esperamos 15 segundos para evitar que el cambio de estado anterior no se haya propagado aún
            sleep(15);
            $request = new Request();
            $request->merge(['comments' => 'La solicitud está siendo procesada.']);
            $mailService->sendMail($request, $certificateRequest->id);
        }
    }
}
