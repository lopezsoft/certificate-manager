<?php

namespace App\Payments\Services;

use App\Payments\Contracts\PaymentGatewayContract;
use App\Payments\DTOs\AcceptanceTokenResponse;
use App\Payments\DTOs\CreateTransactionRequest;
use App\Payments\DTOs\TransactionResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WompiPaymentService
 *
 * Integración REST con WOMPI Payment Gateway (Colombia).
 * Docs: https://docs.wompi.co/
 *
 * Seguridad: nunca loguea private_key ni datos de tarjeta.
 */
class WompiPaymentService implements PaymentGatewayContract
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $publicKey,
        private readonly string $privateKey,
        private readonly string $eventsSecret,
        private readonly string $integrityKey,
    ) {}

    /**
     * Obtiene el token de aceptación de T&C (requerido para crear transacciones).
     * GET /merchants/{public_key}
     */
    public function getAcceptanceToken(): AcceptanceTokenResponse
    {
        Log::info('[WOMPI] Obteniendo acceptance token.');

        $response = Http::timeout(15)
            ->get("{$this->apiUrl}/merchants/{$this->publicKey}");

        if (! $response->successful()) {
            throw new \RuntimeException("WOMPI: error al obtener merchant info. HTTP {$response->status()}");
        }

        return AcceptanceTokenResponse::fromWompiResponse($response->json() ?? []);
    }

    /**
     * Obtiene información del merchant (incluye acceptance_token).
     * GET /merchants/{public_key}
     */
    public function getMerchantInfo(): array
    {
        $response = Http::timeout(15)
            ->get("{$this->apiUrl}/merchants/{$this->publicKey}");

        return $response->json() ?? [];
    }

    /**
     * Crea una transacción de pago.
     * POST /transactions — autenticado con private_key
     */
    public function createTransaction(CreateTransactionRequest $dto): TransactionResponse
    {
        Log::info('[WOMPI] Creando transacción.', [
            'reference'     => $dto->reference,
            'amount_cents'  => $dto->amountInCents,
            'currency'      => $dto->currency,
        ]);

        $response = Http::timeout(30)
            ->withToken($this->privateKey)
            ->post("{$this->apiUrl}/transactions", $dto->toArray());

        if (! $response->successful()) {
            Log::error('[WOMPI] Error al crear transacción.', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException(
                "WOMPI: error al crear transacción. HTTP {$response->status()}"
            );
        }

        return TransactionResponse::fromWompiResponse($response->json() ?? []);
    }

    /**
     * Consulta el estado de una transacción.
     * GET /transactions/{id}
     */
    public function getTransaction(string $transactionId): TransactionResponse
    {
        Log::info('[WOMPI] Consultando transacción.', ['id' => $transactionId]);

        $response = Http::timeout(15)
            ->withToken($this->privateKey)
            ->get("{$this->apiUrl}/transactions/{$transactionId}");

        if (! $response->successful()) {
            throw new \RuntimeException(
                "WOMPI: error al consultar transacción {$transactionId}. HTTP {$response->status()}"
            );
        }

        return TransactionResponse::fromWompiResponse($response->json() ?? []);
    }

    /**
     * Valida la firma HMAC-SHA256 de un evento webhook de WOMPI.
     * Firma = SHA256( transactionId + status + amountInCents + currency + checksum_timestamp + events_secret )
     */
    public function validateWebhookSignature(string $payload, string $checksum, string $timestamp): bool
    {
        $data = json_decode($payload, true);
        $tx   = $data['data']['transaction'] ?? [];

        $chain = implode('', [
            $tx['id']              ?? '',
            $tx['status']          ?? '',
            $tx['amount_in_cents'] ?? '',
            $tx['currency']        ?? '',
            $timestamp,
            $this->eventsSecret,
        ]);

        $expected = hash('sha256', $chain);

        return hash_equals($expected, $checksum);
    }

    /**
     * Anula una transacción aprobada (void).
     * POST /transactions/{id}/void
     */
    public function voidTransaction(string $transactionId): TransactionResponse
    {
        Log::info('[WOMPI] Anulando transacción.', ['id' => $transactionId]);

        $response = Http::timeout(30)
            ->withToken($this->privateKey)
            ->post("{$this->apiUrl}/transactions/{$transactionId}/void");

        if (! $response->successful()) {
            throw new \RuntimeException(
                "WOMPI: error al anular transacción {$transactionId}. HTTP {$response->status()}"
            );
        }

        return TransactionResponse::fromWompiResponse($response->json() ?? []);
    }

    /**
     * Genera el hash de integridad para el widget de checkout.
     * hash = SHA256(reference + amountInCents + currency + integrity_key)
     */
    public function generateIntegrityHash(string $reference, int $amountInCents, string $currency = 'COP'): string
    {
        return hash('sha256', $reference . $amountInCents . $currency . $this->integrityKey);
    }
}

