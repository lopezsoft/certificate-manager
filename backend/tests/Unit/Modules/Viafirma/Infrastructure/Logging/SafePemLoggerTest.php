<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Infrastructure\Logging;

use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class SafePemLoggerTest extends TestCase
{
    private function makeInner(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var array<int,array{level:mixed,message:string,context:array}> */
            public array $records = [];
            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
    }

    public function test_private_key_in_message_is_redacted(): void
    {
        $inner = $this->makeInner();
        $logger = new SafePemLogger($inner);

        $pem = "-----BEGIN PRIVATE KEY-----\nMIIEv...secret...AB==\n-----END PRIVATE KEY-----";
        $logger->info("oops the key is: {$pem}");

        $this->assertStringNotContainsString('MIIEv', $inner->records[0]['message']);
        $this->assertStringContainsString('[REDACTED:PRIVATE_KEY', $inner->records[0]['message']);
    }

    public function test_sensitive_context_keys_are_redacted(): void
    {
        $inner = $this->makeInner();
        $logger = new SafePemLogger($inner);

        $logger->info('ctx', [
            'private_key' => '-----BEGIN PRIVATE KEY-----\nXYZ\n-----END PRIVATE KEY-----',
            'pin'         => '12345678',
            'client_secret' => 'oauth-secret-xyz',
            'safe'        => 'value',
        ]);
        $ctx = $inner->records[0]['context'];
        $this->assertSame('[REDACTED]', $ctx['private_key']);
        $this->assertSame('[REDACTED]', $ctx['pin']);
        $this->assertSame('[REDACTED]', $ctx['client_secret']);
        $this->assertSame('value', $ctx['safe']);
    }

    public function test_csr_in_message_is_redacted(): void
    {
        $inner = $this->makeInner();
        $logger = new SafePemLogger($inner);
        $csr = "-----BEGIN CERTIFICATE REQUEST-----\nMIIBxx==\n-----END CERTIFICATE REQUEST-----";
        $logger->debug($csr);
        $this->assertStringContainsString('[REDACTED:CSR', $inner->records[0]['message']);
    }
}

