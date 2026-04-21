<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests de Feature para los endpoints V2.
 * Usa RefreshDatabase NO — prueba solo acceso y estructura de respuesta.
 */
class V2EndpointsTest extends TestCase
{
    // ── GET /api/v2/pricing (público) ────────────────────────────────────────

    public function test_pricing_endpoint_es_accesible_sin_autenticacion(): void
    {
        $response = $this->getJson('/api/v2/pricing');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['tier', 'min', 'price_1yr', 'price_2yr'],
            ],
        ]);
    }

    public function test_pricing_retorna_3_rangos(): void
    {
        $response = $this->getJson('/api/v2/pricing');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_pricing_primer_rango_es_rango_1(): void
    {
        $response = $this->getJson('/api/v2/pricing');

        $data = $response->json('data');
        $this->assertSame('RANGO_1', $data[0]['tier']);
    }

    // ── Rutas protegidas — 401 sin token ─────────────────────────────────────

    public function test_certificate_request_v2_store_requiere_autenticacion(): void
    {
        $this->postJson('/api/v2/certificate-request', [])->assertStatus(401);
    }

    public function test_andes_identity_start_requiere_autenticacion(): void
    {
        $this->postJson('/api/v2/andes/identity/start', [])->assertStatus(401);
    }

    public function test_andes_identity_verify_otp_requiere_autenticacion(): void
    {
        $this->postJson('/api/v2/andes/identity/verify-otp', [])->assertStatus(401);
    }

    public function test_orders_requiere_autenticacion(): void
    {
        $this->getJson('/api/v2/orders')->assertStatus(401);
    }

    public function test_orders_store_requiere_autenticacion(): void
    {
        $this->postJson('/api/v2/orders', [])->assertStatus(401);
    }

    public function test_admin_quotas_requiere_autenticacion(): void
    {
        $this->getJson('/api/v2/admin/quotas')->assertStatus(401);
    }

    public function test_health_check_requiere_autenticacion(): void
    {
        $this->getJson('/api/v2/health')->assertStatus(401);
    }
}

