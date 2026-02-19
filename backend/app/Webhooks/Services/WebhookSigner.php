<?php

namespace App\Webhooks\Services;

class WebhookSigner
{
    private const ALGORITHM = 'sha256';

    /**
     * Firma el payload usando HMAC-SHA256.
     * Formato compatible con Stripe: t={timestamp},v1={hmac}
     */
    public function sign(string $payload, string $secret): string
    {
        $timestamp = time();
        $hmac      = hash_hmac(self::ALGORITHM, $payload, $secret);

        return "t={$timestamp},v1={$hmac}";
    }

    /**
     * Verifica la firma e incluye protección contra replay attacks (5 minutos).
     */
    public function verify(string $payload, string $signatureHeader, string $secret): bool
    {
        $parts = $this->parseSignature($signatureHeader);

        if (!isset($parts['t'], $parts['v1'])) {
            return false;
        }

        if (abs(time() - (int) $parts['t']) > 300) {
            return false;
        }

        $expected = hash_hmac(self::ALGORITHM, $payload, $secret);

        return hash_equals($expected, $parts['v1']);
    }

    private function parseSignature(string $signature): array
    {
        $result = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $result[trim($key)] = trim($value);
        }

        return $result;
    }
}
