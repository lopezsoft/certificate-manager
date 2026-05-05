<?php

declare(strict_types=1);

namespace App\Quotas\Services;

use App\Payments\Contracts\PaymentGatewayContract;
use App\Payments\DTOs\CreateTransactionRequest;
use App\Payments\Models\PaymentTransaction;
use App\Quotas\Models\CertificateOrder;
use App\Quotas\Models\CertificateOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentOrchestrator
 *
 * Orquesta el flujo de pago agnóstico de pasarela:
 * crear transacción → actualizar orden → crear items.
 *
 * Recibe PaymentGatewayContract (no WompiPaymentService directamente).
 * La conversión a centavos u otros formatos se delega al Adapter.
 */
class PaymentOrchestrator
{
    public function __construct(
        private readonly PaymentGatewayContract $gateway,
    ) {}

    /**
     * Inicia el proceso de pago para una orden.
     * El monto se pasa en valor real (COP). El Adapter lo convierte al formato del proveedor.
     */
    public function initiatePayment(
        CertificateOrder $order,
        string $paymentSourceId,
        string $acceptanceToken,
        string $paymentMethod,
        ?int $installments = 1,
    ): PaymentTransaction {
        $dto = new CreateTransactionRequest(
            amountInCents:   (int) round((float) $order->total_amount * 100),
            currency:        $order->currency,
            reference:       $order->provider_reference,
            customerEmail:   $order->user->email,
            acceptanceToken: $acceptanceToken,
            paymentSourceId: $paymentSourceId,
            paymentMethod:   $paymentMethod,
            installments:    (string) $installments,
        );

        $txResponse = $this->gateway->createTransaction($dto);

        Log::info('[PAYMENT] Transacción creada.', [
            'provider_id' => $txResponse->id,
            'status'      => $txResponse->status->value,
            'order_id'    => $order->id,
            'provider'    => $order->payment_provider ?? 'WOMPI',
        ]);

        return PaymentTransaction::create([
            'certificate_order_id'   => $order->id,
            'payment_provider'       => $order->payment_provider ?? 'WOMPI',
            'provider_transaction_id' => $txResponse->id,
            'provider_reference'     => $txResponse->reference,
            'status'                 => $txResponse->status->value,
            'amount'                 => (float) $txResponse->amountInCents / 100,
            'currency'               => $txResponse->currency,
            'payment_method_type'    => $txResponse->paymentMethodType,
            'provider_raw_response'  => $txResponse->rawResponse,
            'acceptance_token'       => $acceptanceToken,
        ]);
    }

    /**
     * Procesa un webhook de pago. Llamado por ProcessWompiWebhookJob.
     * Actualiza el estado de la transacción y, si APPROVED, crea los items.
     */
    public function processWebhookEvent(array $event): void
    {
        $txData = $event['data']['transaction'] ?? [];
        if (empty($txData)) return;

        $providerTxId = $txData['id'] ?? null;
        $status       = $txData['status'] ?? null;

        if (! $providerTxId || ! $status) return;

        DB::transaction(function () use ($txData, $providerTxId, $status) {
            $transaction = PaymentTransaction::where('provider_transaction_id', $providerTxId)
                ->lockForUpdate()
                ->first();

            if (! $transaction) {
                Log::warning('[PAYMENT] Webhook: transacción no encontrada.', ['provider_id' => $providerTxId]);
                return;
            }

            $transaction->update([
                'status'               => $status,
                'provider_raw_response' => $txData,
                'paid_at'              => $status === 'APPROVED' ? now() : null,
            ]);

            $order = $transaction->order;
            $order->update([
                'status'         => $status === 'APPROVED' ? 'PAID' : ($status === 'DECLINED' ? 'FAILED' : $order->status),
                'payment_method' => $txData['payment_method_type'] ?? $order->payment_method,
            ]);

            if ($status === 'APPROVED') {
                $this->createOrderItems($order);
                Log::info('[PAYMENT] Orden pagada — items creados.', [
                    'order_id' => $order->id,
                    'quantity' => $order->quantity,
                ]);
            }
        });
    }

    private function createOrderItems(CertificateOrder $order): void
    {
        if ($order->items()->count() > 0) return;

        $items = [];
        for ($i = 0; $i < $order->quantity; $i++) {
            $items[] = [
                'certificate_order_id'    => $order->id,
                'certificate_request_id'  => null,
                'status'                  => 'PENDING',
                'created_at'              => now(),
                'updated_at'              => now(),
            ];
        }

        CertificateOrderItem::insert($items);
    }
}
