<?php

namespace Tests\Unit\Quotas;

use App\Quotas\Services\PricingService;
use Tests\TestCase;

/**
 * Tests unitarios para PricingService.
 * Solo lógica pura — sin DB, sin mocks complejos.
 */
class PricingServiceTest extends TestCase
{
    private PricingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PricingService();
    }

    // ── getTierForQuantity ────────────────────────────────────────────────────

    public function test_1_unidad_es_rango_1(): void
    {
        $this->assertSame('RANGO_1', $this->service->getTierForQuantity(1));
    }

    public function test_4_unidades_es_rango_1(): void
    {
        $this->assertSame('RANGO_1', $this->service->getTierForQuantity(4));
    }

    public function test_5_unidades_es_rango_2(): void
    {
        $this->assertSame('RANGO_2', $this->service->getTierForQuantity(5));
    }

    public function test_9_unidades_es_rango_2(): void
    {
        $this->assertSame('RANGO_2', $this->service->getTierForQuantity(9));
    }

    public function test_10_unidades_es_rango_3(): void
    {
        $this->assertSame('RANGO_3', $this->service->getTierForQuantity(10));
    }

    public function test_100_unidades_es_rango_3(): void
    {
        $this->assertSame('RANGO_3', $this->service->getTierForQuantity(100));
    }

    public function test_cantidad_cero_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->getTierForQuantity(0);
    }

    // ── calculatePrice ────────────────────────────────────────────────────────

    public function test_precio_1_certificado_1_anio(): void
    {
        $result = $this->service->calculatePrice(1, 1);

        $this->assertSame('RANGO_1', $result['tier']);
        $this->assertSame(135_000, $result['unit_price']);
        $this->assertSame(135_000, $result['subtotal']);
        $this->assertSame(1, $result['quantity']);
        $this->assertSame(1, $result['vigencia']);
        $this->assertSame('COP', $result['currency']);
        $this->assertArrayHasKey('tax_amount', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertGreaterThan($result['subtotal'], $result['total']);
    }

    public function test_precio_5_certificados_1_anio_rango_2(): void
    {
        $result = $this->service->calculatePrice(5, 1);

        $this->assertSame('RANGO_2', $result['tier']);
        $this->assertSame(125_000, $result['unit_price']);
        $this->assertSame(625_000, $result['subtotal']);
    }

    public function test_precio_10_certificados_2_anios_rango_3(): void
    {
        $result = $this->service->calculatePrice(10, 2);

        $this->assertSame('RANGO_3', $result['tier']);
        $this->assertSame(185_000, $result['unit_price']);
        $this->assertSame(1_850_000, $result['subtotal']);
    }

    public function test_iva_se_calcula_correctamente(): void
    {
        // 5 certificados × $125,000 = $625,000
        // IVA 19% = $118,750
        // Total = $743,750
        $result = $this->service->calculatePrice(5, 1);

        $expectedTax   = (int) round(625_000 * 0.19);
        $expectedTotal = 625_000 + $expectedTax;

        $this->assertSame($expectedTax, $result['tax_amount']);
        $this->assertSame($expectedTotal, $result['total']);
    }

    public function test_vigencia_invalida_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('vigencia');

        $this->service->calculatePrice(5, 3);
    }

    public function test_cantidad_invalida_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->calculatePrice(0, 1);
    }

    // ── getActiveTiers ────────────────────────────────────────────────────────

    public function test_get_active_tiers_retorna_3_rangos(): void
    {
        $tiers = $this->service->getActiveTiers();

        $this->assertCount(3, $tiers);

        $names = array_column($tiers, 'tier');
        $this->assertContains('RANGO_1', $names);
        $this->assertContains('RANGO_2', $names);
        $this->assertContains('RANGO_3', $names);
    }

    public function test_get_active_tiers_estructura_correcta(): void
    {
        $tiers = $this->service->getActiveTiers();

        foreach ($tiers as $tier) {
            $this->assertArrayHasKey('tier', $tier);
            $this->assertArrayHasKey('min', $tier);
            $this->assertArrayHasKey('price_1yr', $tier);
            $this->assertArrayHasKey('price_2yr', $tier);
        }
    }

    public function test_rango_3_max_es_null(): void
    {
        $tiers  = $this->service->getActiveTiers();
        $rango3 = collect($tiers)->firstWhere('tier', 'RANGO_3');

        $this->assertNull($rango3['max']);
    }
}

