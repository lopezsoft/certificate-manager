<?php

namespace App\Payments\Contracts;

use App\Payments\DTOs\AcceptanceTokenResponse;
use App\Payments\DTOs\CreateTransactionRequest;
use App\Payments\DTOs\TransactionResponse;

interface PaymentGatewayContract
{
    public function getAcceptanceToken(): AcceptanceTokenResponse;
    public function getMerchantInfo(): array;
    public function createTransaction(CreateTransactionRequest $dto): TransactionResponse;
    public function getTransaction(string $transactionId): TransactionResponse;
    public function validateWebhookSignature(string $payload, string $checksum, string $timestamp): bool;
    public function voidTransaction(string $transactionId): TransactionResponse;
}

