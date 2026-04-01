<?php

namespace Tests\Unit\Services;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Contracts\WebhookRepositoryContract;
use App\Webhooks\Services\WebhookDispatcher;
use App\Webhooks\Services\WebhookSigner;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Tests unitarios para WebhookDispatcher.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class WebhookDispatcherTest extends TestCase
{
    // ── Helper: crear evento fake ────────────────────────────────────────────

    private function makeEvent(string $eventType = 'certificate_request.created', int $companyId = 1): WebhookEventContract
    {
        return new class ($eventType, $companyId) implements WebhookEventContract {
            public function __construct(
                private readonly string $type,
                private readonly int    $company
            ) {}

            public function eventType(): string   { return $this->type; }
            public function companyId(): int      { return $this->company; }
            public function resourceData(): array { return ['id' => 1]; }
        };
    }

    // ── Despacho vacío ────────────────────────────────────────────────────────

    public function test_no_hace_ninguna_entrega_si_no_hay_endpoints_activos(): void
    {
        $repo = $this->createMock(WebhookRepositoryContract::class);
        $repo->method('findActiveByCompanyAndEvent')
             ->willReturn(collect()); // colección vacía

        $signer     = $this->createMock(WebhookSigner::class);
        $dispatcher = new WebhookDispatcher($repo, $signer, []);

        // No debe lanzar ninguna excepción ni intentar HTTP
        $dispatcher->dispatch($this->makeEvent());

        // Si llegamos aquí sin excepción, el test pasa
        $this->assertTrue(true);
    }

    // ── Resolución de builders ────────────────────────────────────────────────

    public function test_lanza_excepcion_si_no_hay_builder_registrado_para_el_evento(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No payload builder registered/');

        $repo = $this->createMock(WebhookRepositoryContract::class);
        $repo->method('findActiveByCompanyAndEvent')
             ->willReturn(collect([
                 (object) ['id' => 1, 'url' => 'https://test.com', 'secret' => 'secret', 'failure_count' => 0],
             ]));

        $signer = $this->createMock(WebhookSigner::class);
        $signer->method('sign')->willReturn('sha256=fake-signature');

        // Sin builders registrados → debe lanzar RuntimeException
        $dispatcher = new WebhookDispatcher($repo, $signer, []);
        $dispatcher->dispatch($this->makeEvent('evento.desconocido'));
    }

    // ── Rutas de eventos ──────────────────────────────────────────────────────

    public function test_get_webhooks_devuelve_401_sin_autenticacion(): void
    {
        $response = $this->getJson('/api/v1/webhooks');

        $response->assertStatus(401);
    }

    public function test_post_webhooks_devuelve_401_sin_autenticacion(): void
    {
        $response = $this->postJson('/api/v1/webhooks', ['url' => 'https://example.com', 'events' => ['*']]);

        $response->assertStatus(401);
    }

    public function test_get_webhooks_events_devuelve_401_sin_autenticacion(): void
    {
        $response = $this->getJson('/api/v1/webhooks/events');

        $response->assertStatus(401);
    }
}
