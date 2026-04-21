<?php

namespace App\Quotas\Services;

use App\Payments\DTOs\CreateTransactionRequest;
use App\Payments\Models\PaymentTransaction;
use App\Payments\Services\WompiPaymentService;
use App\Quotas\Models\CertificateOrder;
use App\Quotas\Models\CertificateOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentOrchestrator
 *
 * Orquesta el flujo: crear transacción WOMPI → actualizar orden → crear items.
 * Si el pago se aprueba (por webhook), genera los CertificateOrderItems PENDING.
 */
class PaymentOrchestrator
{
    public function __construct(
        private readonly WompiPaymentService $wompi,
    ) {}

    /**
     * Inicia el proceso de pago para una orden.
     * Crea la transacción en WOMPI y la persiste como PENDING.
     */
    public function initiatePayment(
        CertificateOrder $order,
        string $paymentSourceId,
        string $acceptanceToken,
        string $paymentMethod,
        ?int $installments = 1,
    ): PaymentTransaction {
        $dto = new CreateTransactionRequest(
            amountInCents:   $order->getTotalInCents(),
            currency:        $order->currency,
            reference:       $order->wompi_reference,
            customerEmail:   $order->user->email,
            acceptanceToken: $acceptanceToken,
            paymentSourceId: $paymentSourceId,
            paymentMethod:   $paymentMethod,
            installments:    (string) $installments,
        );

        $txResponse = $this->wompi->createTransaction($dto);

        Log::info('[PAYMENT] Transacción WOMPI creada.', [
            'wompi_id'  => $txResponse->id,
            'status'    => $txResponse->status->value,
            'order_id'  => $order->id,
        ]);

        return PaymentTransaction::create([
            'certificate_order_id' => $order->id,
            'wompi_transaction_id' => $txResponse->id,
            'wompi_reference'      => $txResponse->reference,
            'status'               => $txResponse->status->value,
            'amount_in_cents'      => $txResponse->amountInCents,
            'currency'             => $txResponse->currency,
            'payment_method_type'  => $txResponse->paymentMethodType,
            'wompi_raw_response'   => $txResponse->rawResponse,
            'acceptance_token'     => $acceptanceToken,
        ]);
    }

    /**
     * Procesa un webhook de WOMPI. Llamado por ProcessWompiWebhookJob.
     * Actualiza el estado de la transacción y, si APPROVED, crea los items.
     */
    public function processWebhookEvent(array $event): void
    {
        $txData = $event['data']['transaction'] ?? [];
        if (empty($txData)) return;

        $wompiTxId = $txData['id'] ?? null;
        $status    = $txData['status'] ?? null;

        if (! $wompiTxId || ! $status) return;

        DB::transaction(function () use ($txData, $wompiTxId, $status) {
            $transaction = PaymentTransaction::where('wompi_transaction_id', $wompiTxId)
                ->lockForUpdate()
                ->first();

            if (! $transaction) {
                Log::warning('[PAYMENT] Webhook: transacción no encontrada.', ['wompi_id' => $wompiTxId]);
                return;
            }

            $transaction->update([
                'status'             => $status,
                'wompi_raw_response' => $txData,
                'paid_at'            => $status === 'APPROVED' ? now() : null,
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
        // Solo crear si no existen ya
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

