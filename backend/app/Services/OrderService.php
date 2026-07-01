<?php

namespace App\Services;

use App\Models\CertificateOrder;
use Illuminate\Support\Str;

/**
 * OrderService
 *
 * Crea órdenes de compra de certificados.
 */
class OrderService
{
    public function __construct(
        private readonly PricingService $pricingService,
    ) {}

    /**
     * Crea una CertificateOrder PENDING con precios calculados.
     * También crea los items PREPAID asociados con la vigencia especificada.
     */
    public function createOrder(int $companyId, int $userId, int $quantity, int $vigencia, int $userTypeId): CertificateOrder
    {
        $pricing = $this->pricingService->calculatePrice($quantity, $vigencia, $userTypeId, $companyId);

        $order = CertificateOrder::create([
            'company_id'         => $companyId,
            'user_id'            => $userId,
            'quantity'           => $quantity,
            'vigencia'           => $vigencia,
            'unit_price'         => $pricing['unit_price'],
            'subtotal'           => $pricing['subtotal'],
            'tax_amount'         => $pricing['tax_amount'],
            'total_amount'       => $pricing['total'],
            'currency'           => $pricing['currency'],
            'status'             => 'PENDING',
            'payment_provider'   => config('payments.default_provider', 'WOMPI'),
            'provider_reference' => $this->generateProviderReference(),
        ]);

        // Refrescar para obtener el UUID generado por el trigger de BD
        $order = $order->refresh();

        // Crear items PREPAID con la vigencia especificada
        for ($i = 0; $i < $quantity; $i++) {
            $order->items()->create([
                'status'   => 'PENDING',
                'vigencia' => $vigencia,
            ]);
        }

        return $order;
    }

    /**
     * Genera una referencia de pago única y trazable.
     * Formato: {prefix}-{name}-{timestamp}-{random}
     * Ejemplo: APP3-CERTS-20260629101546-ABC123
     */
    private function generateProviderReference(): string
    {
        $prefix = config('wompi.reference_prefix', 'APP3');
        $name = config('wompi.reference_name', 'CERTS');
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(Str::random(6));

        return "{$prefix}-{$name}-{$timestamp}-{$random}";
    }
}
