<?php

namespace Tests\Unit\Services;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use App\Models\Company;
use App\Services\PricingService;
use Tests\TestCase;

/**
 * Tests para PricingService — cálculo de tier basado en cantidad efectiva.
 *
 * Verifica que resolveEffectiveQuantity cuente correctamente:
 * - Certificados vigentes (expiration_date IS NULL OR expiration_date > NOW())
 * - Solo en estados de emisión (PROCESSED, PROCESSING)
 * - Solicitados en el año anterior O el año actual (created_at)
 * - Suma con la compra actual para determinar el tier
 */
class PricingServiceTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private PricingService $service;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PricingService();

        // Crear empresa de prueba (user_type_id se pasa en calculatePrice)
        $this->company = Company::factory()->create();
    }

    // ── Vigencia (expiration_date) ────────────────────────────────────────────

    public function test_vigente_procesado_sin_vencimiento_se_cuenta(): void
    {
        CertificateRequest::factory()
            ->forCompany($this->company->id)
            ->processed()
            ->withExpirationDate(null)
            ->create(['created_at' => now()->subMonths(3)]);

        // calculatePrice con 1 unidad: debería usar effective_quantity = 1 (vigente) + 1 (actual) = 2
        // Tier para 2 unidades debería ser RANGO_1 (min 1, max 4)
        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        $this->assertSame('RANGO_1', $result['tier']);
        $this->assertSame(2, $result['effective_quantity']);
    }

    public function test_vigente_vencido_no_se_cuenta(): void
    {
        // Certificado con expiration_date en el pasado (vencido)
        CertificateRequest::factory()
            ->forCompany($this->company->id)
            ->processed()
            ->withExpirationDate(now()->subDay())
            ->create(['created_at' => now()->subMonths(3)]);

        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        // Sin certificados vigentes: effective_quantity = 0 + 1 = 1 → RANGO_1
        $this->assertSame('RANGO_1', $result['tier']);
        $this->assertSame(1, $result['effective_quantity']);
    }

    public function test_processing_sin_vencimiento_se_cuenta(): void
    {
        // Certificado PROCESSING (aún siendo procesado) sin expiration_date
        CertificateRequest::factory()
            ->forCompany($this->company->id)
            ->processing()
            ->withExpirationDate(null)
            ->create(['created_at' => now()]);

        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        $this->assertSame(2, $result['effective_quantity']);
    }

    public function test_processing_vencido_no_se_cuenta(): void
    {
        // PROCESSING pero con fecha vencida
        CertificateRequest::factory()
            ->forCompany($this->company->id)
            ->processing()
            ->withExpirationDate(now()->subDay())
            ->create(['created_at' => now()]);

        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        $this->assertSame(1, $result['effective_quantity']);
    }

    public function test_expired_status_no_se_cuenta(): void
    {
        // Status EXPIRED — nunca se cuenta sin importar expiration_date
        CertificateRequest::factory()
            ->forCompany($this->company->id)
            ->expired()
            ->create(['created_at' => now()->subMonths(3)]);

        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        $this->assertSame(1, $result['effective_quantity']);
    }

    // ── Ventana de año (created_at en año anterior/actual) ────────────────────

    public function test_solicitado_ano_anterior_vigente_se_cuenta(): void
    {
        // Creado en el año anterior, sin vencer
        $lastYearDate = now()->subYears(1)->setMonth(6);
        CertificateRequest::factory()
            ->forCompany($this->company->id)
            ->processed()
            ->withExpirationDate(now()->addYears(2))
            ->create(['created_at' => $lastYearDate]);

        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        $this->assertSame(2, $result['effective_quantity']);
    }

    public function test_solicitado_ano_actual_vigente_se_cuenta(): void
    {
        // Creado este año, sin vencer
        $thisYearDate = now()->setMonth(6);
        CertificateRequest::factory()
            ->forCompany($this->company->id)
            ->processed()
            ->withExpirationDate(now()->addYears(2))
            ->create(['created_at' => $thisYearDate]);

        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        $this->assertSame(2, $result['effective_quantity']);
    }

    public function test_life_2_vencimiento_ajustado_por_vida(): void
    {
        // life=2: se resta 1 año del expiration_date para el cálculo de vigencia
        // Creado este año con life=2, expiration_date = hoy + 1.5 años
        // Para life=2, vigente si expiration_date > now() + 1 año
        // hoy + 1.5 años > hoy + 1 año? SÍ → cuenta
        CertificateRequest::factory()
            ->forCompany($this->company->id)
            ->processed()
            ->withExpirationDate(now()->addMonths(18)) // 1.5 años
            ->create([
                'created_at' => now(),
                'life'       => 2,
            ]);

        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        // Cuenta porque expiration_date (now + 18m) > now + 12m
        $this->assertSame(2, $result['effective_quantity']);
    }

    public function test_life_2_vencido_al_ano_no_se_cuenta(): void
    {
        // life=2: expiration_date = hoy + 11 meses
        // Para life=2, vigente si expiration_date > now() + 1 año
        // hoy + 11m > hoy + 12m? NO → no cuenta
        CertificateRequest::factory()
            ->forCompany($this->company->id)
            ->processed()
            ->withExpirationDate(now()->addMonths(11)) // Menos de 1 año
            ->create([
                'created_at' => now(),
                'life'       => 2,
            ]);

        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        // No cuenta porque expiration_date (now + 11m) <= now + 12m
        $this->assertSame(1, $result['effective_quantity']);
    }

    public function test_solicitado_hace_2_anos_vigente_no_se_cuenta(): void
    {
        // Creado hace 2 años, pero aún vigente (life=2)
        // Está fuera de la ventana año_anterior/actual, así que no cuenta
        $twoYearsAgo = now()->subYears(2)->setMonth(6);
        CertificateRequest::factory()
            ->forCompany($this->company->id)
            ->processed()
            ->withExpirationDate(now()->addYears(1)) // Aún vigente por 1 año más
            ->create([
                'created_at' => $twoYearsAgo,
                'life'       => 2,
            ]);

        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        // No cuenta por estar fuera de ventana, aunque vigente
        $this->assertSame(1, $result['effective_quantity']);
    }

    public function test_solicitado_hace_3_anos_no_se_cuenta(): void
    {
        // Creado hace 3+ años (muy antiguo)
        $threeYearsAgo = now()->subYears(3)->setMonth(6);
        CertificateRequest::factory()
            ->forCompany($this->company->id)
            ->processed()
            ->withExpirationDate(now()->addYears(1))
            ->create(['created_at' => $threeYearsAgo]);

        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        $this->assertSame(1, $result['effective_quantity']);
    }

    // ── Cantidad efectiva y tier ──────────────────────────────────────────────

    public function test_multiple_vigentes_incrementan_tier(): void
    {
        // 5 certificados vigentes + 1 compra actual = 6, debería ser RANGO_2 (5-9)
        for ($i = 0; $i < 5; $i++) {
            CertificateRequest::factory()
                ->forCompany($this->company->id)
                ->processed()
                ->withExpirationDate(now()->addYears(1))
                ->create(['created_at' => now()->subMonths(3)]);
        }

        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        $this->assertSame(6, $result['effective_quantity']);
        $this->assertSame('RANGO_2', $result['tier']);
    }

    public function test_cantidad_actual_comprada_no_esta_en_vigentes(): void
    {
        // 9 vigentes en BD, compra 1 nueva → effective = 10 → RANGO_3
        // El precio se calcula por la cantidad COMPRADA (1), pero el tier por la effective (10)
        for ($i = 0; $i < 9; $i++) {
            CertificateRequest::factory()
                ->forCompany($this->company->id)
                ->processed()
                ->withExpirationDate(now()->addYears(1))
                ->create(['created_at' => now()->subMonths(3)]);
        }

        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        $this->assertSame(10, $result['effective_quantity']);
        $this->assertSame('RANGO_3', $result['tier']);
        $this->assertSame(1, $result['quantity']); // Cantidad FACTURADA es 1
    }

    public function test_minimo_effective_quantity_es_1(): void
    {
        // Sin certificados vigentes y compra de 1 → effective_quantity = 1 (mínimo)
        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        $this->assertSame(1, $result['effective_quantity']);
        $this->assertGreaterThanOrEqual(1, $result['effective_quantity']);
    }

    // ── user_type_id no volumen-based ────────────────────────────────────────

    public function test_user_type_no_volume_based_ignora_historial(): void
    {
        // user_type_id = 1 (no es 3 o 4) → historial ignorado
        for ($i = 0; $i < 10; $i++) {
            CertificateRequest::factory()
                ->forCompany($this->company->id)
                ->processed()
                ->withExpirationDate(now()->addYears(1))
                ->create(['created_at' => now()]);
        }

        // Para user_type_id 1, solo se usa quantity actual
        $result = $this->service->calculatePrice(5, 1, 1, $this->company->id);

        $this->assertSame(5, $result['effective_quantity']);
        $this->assertSame('RANGO_1', $result['tier']);
    }

    // ── Diferentes empresas ──────────────────────────────────────────────────

    public function test_certificados_otra_empresa_no_se_cuentan(): void
    {
        $otherCompany = Company::factory()->create(['user_type_id' => 3]);

        // 10 certificados vigentes de OTRA empresa
        for ($i = 0; $i < 10; $i++) {
            CertificateRequest::factory()
                ->forCompany($otherCompany->id)
                ->processed()
                ->withExpirationDate(now()->addYears(1))
                ->create(['created_at' => now()]);
        }

        // Consultar precio para $this->company (sin certificados)
        $result = $this->service->calculatePrice(1, 1, 3, $this->company->id);

        $this->assertSame(1, $result['effective_quantity']);
        // Los 10 de otra empresa no afectan
    }
}
