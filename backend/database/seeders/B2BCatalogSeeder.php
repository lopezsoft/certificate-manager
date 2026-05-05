<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Seeder para catálogos base del modelo B2B.
 *
 * Ejecutar individualmente:
 * php artisan db:seed --class=B2BCatalogSeeder
 */
class B2BCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // ── company_types ────────────────────────────────────────────
        DB::table('company_types')->insertOrIgnore([
            [
                'code'        => 'API_DEVELOPER',
                'name'        => 'Desarrollador API',
                'description' => 'Empresa que integra nuestra API de certificados en su ERP o plataforma.',
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'code'        => 'PORTAL_ALLY',
                'name'        => 'Aliado del Portal',
                'description' => 'Empresa externa que usa el portal web para solicitar certificados.',
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'code'        => 'ERP_PARTNER',
                'name'        => 'Partner ERP',
                'description' => 'Distribuidor que integra MATIAS ERP con el servicio de certificados.',
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);

        // ── pricing_tiers (valores en COP real, NO centavos) ─────────
        DB::table('pricing_tiers')->insertOrIgnore([
            [
                'code'         => 'RANGO_1',
                'name'         => 'Básico',
                'min_quantity' => 1,
                'max_quantity' => 4,
                'price_1yr'    => 135000.00,
                'price_2yr'    => 215000.00,
                'is_active'    => true,
                'sort_order'   => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'code'         => 'RANGO_2',
                'name'         => 'Profesional',
                'min_quantity' => 5,
                'max_quantity' => 9,
                'price_1yr'    => 125000.00,
                'price_2yr'    => 200000.00,
                'is_active'    => true,
                'sort_order'   => 2,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'code'         => 'RANGO_3',
                'name'         => 'Enterprise',
                'min_quantity' => 10,
                'max_quantity' => null,
                'price_1yr'    => 115000.00,
                'price_2yr'    => 185000.00,
                'is_active'    => true,
                'sort_order'   => 3,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
        ]);
    }
}
