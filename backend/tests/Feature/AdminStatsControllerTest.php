<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminStatsService;
use Tests\TestCase;

/**
 * Tests de característica para AdminStatsController.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class AdminStatsControllerTest extends TestCase
{
    // ── Autenticación / autorización ─────────────────────────────────────────

    public function test_payments_by_month_devuelve_401_sin_autenticacion(): void
    {
        $this->getJson('/api/v1/admin/stats/payments-by-month')->assertStatus(401);
    }

    public function test_unused_quotas_devuelve_401_sin_autenticacion(): void
    {
        $this->getJson('/api/v1/admin/stats/unused-quotas')->assertStatus(401);
    }

    public function test_payments_by_month_devuelve_403_si_no_es_admin(): void
    {
        $user = User::make(['id' => 1, 'email' => 'user@test.com', 'type_id' => 2]);

        $this->actingAs($user, 'api')
            ->getJson('/api/v1/admin/stats/payments-by-month')
            ->assertStatus(403);
    }

    public function test_unused_quotas_devuelve_403_si_no_es_admin(): void
    {
        $user = User::make(['id' => 1, 'email' => 'user@test.com', 'type_id' => 2]);

        $this->actingAs($user, 'api')
            ->getJson('/api/v1/admin/stats/unused-quotas')
            ->assertStatus(403);
    }

    // ── Validación ───────────────────────────────────────────────────────────

    public function test_payments_by_month_devuelve_422_con_anio_invalido(): void
    {
        $this->mock(AdminStatsService::class)->shouldNotReceive('paymentsByMonth');

        $this->actingAs($this->admin(), 'api')
            ->getJson('/api/v1/admin/stats/payments-by-month?year=1990')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year']);
    }

    public function test_unused_quotas_devuelve_422_con_company_id_invalido(): void
    {
        $this->mock(AdminStatsService::class)->shouldNotReceive('unusedQuotas');

        $this->actingAs($this->admin(), 'api')
            ->getJson('/api/v1/admin/stats/unused-quotas?company_id=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);
    }

    // ── Camino feliz (servicio mockeado) ─────────────────────────────────────

    public function test_payments_by_month_usa_anio_actual_por_defecto_y_retorna_estructura(): void
    {
        $payload = [
            'year'            => now()->year,
            'available_years' => [now()->year],
            'months'          => [],
            'companies'       => [],
            'totals'          => ['companies' => 0, 'orders' => 0, 'certificates' => 0, 'subtotal' => 0, 'tax_amount' => 0, 'total_amount' => 0],
        ];

        $this->mock(AdminStatsService::class)
            ->shouldReceive('paymentsByMonth')
            ->once()
            ->with(now()->year, null)
            ->andReturn($payload);

        $this->actingAs($this->admin(), 'api')
            ->getJson('/api/v1/admin/stats/payments-by-month')
            ->assertOk()
            ->assertJsonPath('dataRecords.year', now()->year)
            ->assertJsonStructure(['dataRecords' => ['year', 'available_years', 'months', 'companies', 'totals']]);
    }

    public function test_payments_by_month_propaga_filtros_al_servicio(): void
    {
        $this->mock(AdminStatsService::class)
            ->shouldReceive('paymentsByMonth')
            ->once()
            ->with(2025, 7)
            ->andReturn(['year' => 2025, 'available_years' => [], 'months' => [], 'companies' => [], 'totals' => []]);

        $this->actingAs($this->admin(), 'api')
            ->getJson('/api/v1/admin/stats/payments-by-month?year=2025&company_id=7')
            ->assertOk();
    }

    public function test_unused_quotas_retorna_total_de_empresas(): void
    {
        $this->mock(AdminStatsService::class)
            ->shouldReceive('unusedQuotas')
            ->once()
            ->with(null, true)
            ->andReturn([
                'include_expired' => true,
                'companies'       => [['company_id' => 1], ['company_id' => 2]],
                'totals'          => ['companies' => 2, 'postpaid_remaining' => 5, 'prepaid_pending' => 3, 'total_unused' => 8],
            ]);

        $this->actingAs($this->admin(), 'api')
            ->getJson('/api/v1/admin/stats/unused-quotas?include_expired=1')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('dataRecords.totals.total_unused', 8);
    }

    private function admin(): User
    {
        return User::make(['id' => 99, 'email' => 'admin@test.com', 'type_id' => 1]);
    }
}
