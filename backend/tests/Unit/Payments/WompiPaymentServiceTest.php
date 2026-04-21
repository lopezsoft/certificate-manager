<?php

namespace Tests\Unit\Payments;

use App\Payments\Services\WompiPaymentService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests unitarios para WompiPaymentService.
 * Usa Http::fake() para simular respuestas de WOMPI.
 */
class WompiPaymentServiceTest extends TestCase
{
    private const API_URL      = 'https://sandbox.wompi.co/v1';
    private const PUBLIC_KEY   = 'pub_test_ABC123';
    private const PRIVATE_KEY  = 'prv_test_XYZ789';
    private const EVENTS_SECRET = 'secret123';
    private const INTEGRITY_KEY = 'int_key_abc';

    private WompiPaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WompiPaymentService(
            apiUrl:       self::API_URL,
            publicKey:    self::PUBLIC_KEY,
            privateKey:   self::PRIVATE_KEY,
            eventsSecret: self::EVENTS_SECRET,
            integrityKey: self::INTEGRITY_KEY,
        );
    }

    // ── getAcceptanceToken ────────────────────────────────────────────────────

    public function test_get_acceptance_token_extrae_token_correctamente(): void
    {
        Http::fake([
            self::API_URL . '/merchants/*' => Http::response([
                'data' => [
                    'presigned_acceptance' => [
                        'acceptance_token' => 'tok_accept_123',
                        'permalink'        => 'https://wompi.co/terms',
                        'type'             => 'END_USER_POLICY',
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->getAcceptanceToken();

        $this->assertSame('tok_accept_123', $result->token);
        $this->assertSame('https://wompi.co/terms', $result->permalink);
    }

    // ── createTransaction ─────────────────────────────────────────────────────

    public function test_create_transaction_retorna_transaccion_pending(): void
    {
        Http::fake([
            self::API_URL . '/transactions' => Http::response([
                'data' => [
                    'id'                 => 'TRX-001',
                    'reference'          => 'ORD-ABC123',
                    'status'             => 'PENDING',
                    'amount_in_cents'    => 88375000,
                    'currency'           => 'COP',
                    'payment_method_type'=> 'CARD',
                ],
            ], 201),
        ]);

        $dto = new \App\Payments\DTOs\CreateTransactionRequest(
            amountInCents:   88375000,
            currency:        'COP',
            reference:       'ORD-ABC123',
            customerEmail:   'user@test.com',
            acceptanceToken: 'tok_accept_123',
            paymentSourceId: 'tok_card_XYZ',
            paymentMethod:   'CARD',
        );

        $result = $this->service->createTransaction($dto);

        $this->assertSame('TRX-001', $result->id);
        $this->assertSame('PENDING', $result->status->value);
        $this->assertFalse($result->isApproved());
    }

    // ── getTransaction ────────────────────────────────────────────────────────

    public function test_get_transaction_approved_retorna_approved(): void
    {
        Http::fake([
            self::API_URL . '/transactions/TRX-001' => Http::response([
                'data' => [
                    'id'                  => 'TRX-001',
                    'reference'           => 'ORD-ABC123',
                    'status'              => 'APPROVED',
                    'amount_in_cents'     => 88375000,
                    'currency'            => 'COP',
                    'payment_method_type' => 'CARD',
                ],
            ], 200),
        ]);

        $result = $this->service->getTransaction('TRX-001');

        $this->assertTrue($result->isApproved());
        $this->assertSame('APPROVED', $result->status->value);
    }

    // ── validateWebhookSignature ──────────────────────────────────────────────

    public function test_valida_firma_correcta(): void
    {
        $txId      = 'TRX-001';
        $status    = 'APPROVED';
        $amount    = '88375000';
        $currency  = 'COP';
        $timestamp = '1714000000';

        $chain    = $txId . $status . $amount . $currency . $timestamp . self::EVENTS_SECRET;
        $checksum = hash('sha256', $chain);

        $payload = json_encode([
            'event' => 'transaction.updated',
            'data'  => [
                'transaction' => [
                    'id'              => $txId,
                    'status'          => $status,
                    'amount_in_cents' => $amount,
                    'currency'        => $currency,
                ],
            ],
        ]);

        $isValid = $this->service->validateWebhookSignature($payload, $checksum, $timestamp);

        $this->assertTrue($isValid);
    }

    public function test_rechaza_firma_incorrecta(): void
    {
        $payload = json_encode(['data' => ['transaction' => []]]);
        $isValid = $this->service->validateWebhookSignature($payload, 'firma-falsa', '123');

        $this->assertFalse($isValid);
    }

    // ── generateIntegrityHash ─────────────────────────────────────────────────

    public function test_genera_hash_de_integridad_correctamente(): void
    {
        $hash = $this->service->generateIntegrityHash('ORD-123', 88375000, 'COP');

        $expected = hash('sha256', 'ORD-123' . 88375000 . 'COP' . self::INTEGRITY_KEY);
        $this->assertSame($expected, $hash);
    }
}

