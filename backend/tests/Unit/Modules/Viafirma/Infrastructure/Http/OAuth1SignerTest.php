<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Infrastructure\Http;

use App\Modules\Viafirma\Infrastructure\Http\OAuth1Signer;
use PHPUnit\Framework\TestCase;

final class OAuth1SignerTest extends TestCase
{
    public function test_rfc3986_encoding_handles_tilde_and_space(): void
    {
        $s = new OAuth1Signer('key', 'secret');
        $this->assertSame('a%20b~c', $s->rfc3986encode('a b~c'));
    }

    /**
     * Vector de referencia adaptado del RFC 5849 §3.4.1.1 con valores deterministas
     * para verificar que la base string es estable.
     */
    public function test_signature_base_string_is_stable(): void
    {
        $s = new OAuth1Signer('ck', 'cs');
        $base = $s->buildSignatureBaseString(
            method: 'GET',
            url: 'https://example.com/ra/available-profiles',
            params: [
                'oauth_consumer_key'     => 'ck',
                'oauth_nonce'            => 'abc',
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp'        => '1700000000',
                'oauth_version'          => '1.0',
                'codRa'                  => 'viafirmaco',
            ],
        );

        $this->assertStringStartsWith('GET&', $base);
        $this->assertStringContainsString('https%3A%2F%2Fexample.com%2Fra%2Favailable-profiles', $base);
        $this->assertStringContainsString('codRa%3Dviafirmaco', $base);
        $this->assertStringContainsString('oauth_consumer_key%3Dck', $base);
    }

    public function test_authorization_header_format(): void
    {
        $s = new OAuth1Signer('mykey', 'mysecret');
        $hdr = $s->buildAuthorizationHeader(
            method: 'GET',
            url: 'https://example.com/ra/available-profiles',
            queryParams: ['codRa' => 'viafirmaco'],
            nonce: 'fixednonce',
            timestamp: 1700000000,
        );

        $this->assertStringStartsWith('OAuth ', $hdr);
        $this->assertStringContainsString('oauth_consumer_key="mykey"', $hdr);
        $this->assertStringContainsString('oauth_nonce="fixednonce"', $hdr);
        $this->assertStringContainsString('oauth_signature_method="HMAC-SHA1"', $hdr);
        $this->assertStringContainsString('oauth_timestamp="1700000000"', $hdr);
        $this->assertStringContainsString('oauth_version="1.0"', $hdr);
        $this->assertMatchesRegularExpression('/oauth_signature="[^"]+"/', $hdr);
    }
}


