<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Infrastructure;

use App\Modules\Viafirma\Infrastructure\Http\N8nKycWebhookClient;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 100% mockeado — no toca base de datos. HTTP interceptado con Http::fake(),
 * el logger es un mock. Cubre la normalización de `companies.phone` con los
 * formatos reales reportados (guiones, puntos, espacios, paréntesis).
 */
final class N8nKycWebhookClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function no_hace_nada_si_no_hay_url_configurada(): void
    {
        config(['services.n8n.kyc_webhook_url' => '']);
        Http::fake();

        $client = new N8nKycWebhookClient(Mockery::mock(SafePemLogger::class));
        $client->notify('ACME', '3001234567', [['codigo' => 'X', 'enlace' => 'https://x', 'nombre' => 'Juan Perez']], 'inmediato');

        Http::assertNothingSent();
    }

    #[Test]
    public function no_hace_nada_si_no_hay_solicitudes(): void
    {
        config(['services.n8n.kyc_webhook_url' => 'https://n8n.example.com/webhook/kyc-notification']);
        Http::fake();

        $client = new N8nKycWebhookClient(Mockery::mock(SafePemLogger::class));
        $client->notify('ACME', '3001234567', [], 'inmediato');

        Http::assertNothingSent();
    }

    #[Test]
    public function registra_warning_y_no_envia_si_no_hay_whatsapp_valido(): void
    {
        config(['services.n8n.kyc_webhook_url' => 'https://n8n.example.com/webhook/kyc-notification']);
        Http::fake();

        $logger = Mockery::mock(SafePemLogger::class);
        $logger->shouldReceive('warning')->once()->with('n8n.kyc_webhook.no_whatsapp', Mockery::type('array'));

        $client = new N8nKycWebhookClient($logger);
        $client->notify('ACME', null, [['codigo' => 'X', 'enlace' => 'https://x', 'nombre' => 'Juan Perez']], 'inmediato');

        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function whatsappFormatsProvider(): array
    {
        return [
            'guiones y puntos'      => ['300-782.48.44', '573007824844'],
            'parentesis y guion'    => ['(300) 782-4844', '573007824844'],
            'espacios'              => ['300 782 4844', '573007824844'],
            'sin separadores'       => ['3007824844', '573007824844'],
            'ya con indicativo'     => ['573007824844', '573007824844'],
        ];
    }

    #[Test]
    public function normaliza_los_formatos_reales_de_companies_phone(): void
    {
        config(['services.n8n.kyc_webhook_url' => 'https://n8n.example.com/webhook/kyc-notification']);

        foreach (self::whatsappFormatsProvider() as [$input, $expected]) {
            Http::fake(['n8n.example.com/*' => Http::response(['ok' => true], 200)]);

            $logger = Mockery::mock(SafePemLogger::class);
            $logger->shouldReceive('info')->once();

            $client = new N8nKycWebhookClient($logger);
            $client->notify('ACME', $input, [['codigo' => 'X', 'enlace' => 'https://x', 'nombre' => 'Juan Perez']], 'inmediato');

            Http::assertSent(fn (Request $request) => $request->data()['whatsapp'] === $expected);
        }
    }

    #[Test]
    public function envia_el_payload_completo_con_la_estructura_esperada(): void
    {
        config(['services.n8n.kyc_webhook_url' => 'https://n8n.example.com/webhook/kyc-notification']);
        Http::fake(['n8n.example.com/*' => Http::response(['ok' => true], 200)]);

        $logger = Mockery::mock(SafePemLogger::class);
        $logger->shouldReceive('info')->once()->with('n8n.kyc_webhook.sent', Mockery::type('array'));

        $client = new N8nKycWebhookClient($logger);
        $client->notify(
            'OFILAFT',
            '573001234567',
            [
                ['codigo' => 'P386BY149', 'enlace' => 'https://viafirma.com/kyc/1', 'nombre' => 'Juan Perez'],
                ['codigo' => 'MEFBND7DG', 'enlace' => 'https://viafirma.com/kyc/2', 'nombre' => 'Maria Lopez (ACME SAS)'],
            ],
            'recordatorio',
        );

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === 'https://n8n.example.com/webhook/kyc-notification'
                && $body['casa_software'] === 'OFILAFT'
                && $body['whatsapp'] === '573001234567'
                && $body['count'] === 2
                && $body['tipo'] === 'recordatorio'
                && $body['solicitudes'][0] === ['codigo' => 'P386BY149', 'enlace' => 'https://viafirma.com/kyc/1', 'nombre' => 'Juan Perez']
                && $body['solicitudes'][1] === ['codigo' => 'MEFBND7DG', 'enlace' => 'https://viafirma.com/kyc/2', 'nombre' => 'Maria Lopez (ACME SAS)'];
        });
    }

    #[Test]
    public function registra_warning_en_respuesta_no_2xx_sin_lanzar_excepcion(): void
    {
        config(['services.n8n.kyc_webhook_url' => 'https://n8n.example.com/webhook/kyc-notification']);
        Http::fake(['n8n.example.com/*' => Http::response(['error' => 'bad'], 500)]);

        $logger = Mockery::mock(SafePemLogger::class);
        $logger->shouldReceive('warning')->once()->with('n8n.kyc_webhook.non_2xx', Mockery::type('array'));

        $client = new N8nKycWebhookClient($logger);
        $client->notify('ACME', '3001234567', [['codigo' => 'X', 'enlace' => 'https://x', 'nombre' => 'Juan Perez']], 'inmediato');

        Http::assertSentCount(1);
    }

    #[Test]
    public function registra_warning_si_la_peticion_lanza_excepcion_sin_relanzar(): void
    {
        config(['services.n8n.kyc_webhook_url' => 'https://n8n.example.com/webhook/kyc-notification']);
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timeout');
        });

        $logger = Mockery::mock(SafePemLogger::class);
        $logger->shouldReceive('warning')->once()->with('n8n.kyc_webhook.failed', Mockery::type('array'));

        $client = new N8nKycWebhookClient($logger);

        // No debe lanzar — fire-and-forget.
        $client->notify('ACME', '3001234567', [['codigo' => 'X', 'enlace' => 'https://x', 'nombre' => 'Juan Perez']], 'inmediato');
        $this->assertTrue(true);
    }
}
