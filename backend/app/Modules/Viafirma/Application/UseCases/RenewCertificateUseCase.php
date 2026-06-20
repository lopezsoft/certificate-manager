<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\UseCases;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateOrder;
use App\Models\CertificateRequest;
use App\Services\PricingService;
use Illuminate\Support\Str;

final class RenewCertificateUseCase
{
    public function __construct(
        private readonly PricingService $pricingService,
    ) {}

    /**
     * Crea una orden de pago para renovar un certificado existente.
     */
    public function handle(int $certificateRequestId, int $userId): CertificateOrder
    {
        $cr = CertificateRequest::findOrFail($certificateRequestId);

        if ($cr->request_status !== CertificateRequestStatusEnum::PROCESSED->value) {
            throw new \Exception('Solo se pueden renovar certificados emitidos (PROCESSED).');
        }

        // Si ya está renovado (life >= 2), no permitir renovarlo de nuevo por ahora.
        if ($cr->life >= 2) {
            throw new \Exception('Este certificado ya tiene la vigencia máxima (2 años).');
        }

        // Obtener el precio para 1 año de renovación
        // quantity=1, vigencia=1
        // Nota: Asumimos que el userTypeId es el del creador original, o se podría pasar
        $userTypeId = $cr->company->user_type_id ?? 1;

        $pricing = $this->pricingService->calculatePrice(1, 1, $userTypeId, $cr->company_id);

        $order = CertificateOrder::create([
            'order_type'             => 'CERTIFICATE_RENEWAL',
            'certificate_request_id' => $cr->id,
            'company_id'             => $cr->company_id,
            'user_id'                => $userId,
            'quantity'               => 1,
            'vigencia'               => 1, // Vigencia que se añade
            'unit_price'             => $pricing['unit_price'],
            'subtotal'               => $pricing['subtotal'],
            'tax_amount'             => $pricing['tax_amount'],
            'total_amount'           => $pricing['total'],
            'currency'               => $pricing['currency'],
            'status'                 => 'PENDING',
            'payment_provider'       => config('payments.default_provider', 'WOMPI'),
            'provider_reference'     => 'RNW-' . strtoupper(Str::random(12)),
        ]);

        return $order->refresh();
    }
}
