<?php

namespace App\Quotas\Services;

use App\Quotas\Models\CertificateOrder;
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
     */
    public function createOrder(int $companyId, int $userId, int $quantity, int $vigencia): CertificateOrder
    {
        $pricing = $this->pricingService->calculatePrice($quantity, $vigencia);

        return CertificateOrder::create([
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
            'provider_reference' => 'ORD-' . strtoupper(Str::random(12)),
        ]);
    }
}

