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
                'code'         => 'INCLUDED',
                'name'         => 'Incluido',
                'min_quantity' => 1,
                'max_quantity' => 10000,
                'price_1yr'    => 31500.00,
                'price_2yr'    => 63000.00,
                'is_active'    => true,
                'sort_order'   => 0,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'code'         => 'PARTNER_RANGE_1',
                'name'         => 'Básico',
                'min_quantity' => 1,
                'max_quantity' => 20,
                'price_1yr'    => 95000.00,
                'price_2yr'    => 190000.00,
                'is_active'    => true,
                'sort_order'   => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'code'         => 'PARTNER_RANGE_2',
                'name'         => 'Profesional',
                'min_quantity' => 21,
                'max_quantity' => 100,
                'price_1yr'    => 83000.00,
                'price_2yr'    => 166000.00,
                'is_active'    => true,
                'sort_order'   => 2,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'code'         => 'PARTNER_RANGE_3',
                'name'         => 'Enterprise',
                'min_quantity' => 101,
                'max_quantity' => 1000,
                'price_1yr'    => 72000.00,
                'price_2yr'    => 144000.00,
                'is_active'    => true,
                'sort_order'   => 3,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'code'         => 'SOFTWARE_HOUSE',
                'name'         => 'Casa de Software',
                'min_quantity' => 1,
                'max_quantity' => 10000,
                'price_1yr'    => 104000.00,
                'price_2yr'    => 208000.00,
                'is_active'    => true,
                'sort_order'   => 4,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'code'         => 'SERVER_RANGE_1',
                'name'         => 'Básico',
                'min_quantity' => 1,
                'max_quantity' => 20,
                'price_1yr'    => 120000.00,
                'price_2yr'    => 240000.00,
                'is_active'    => true,
                'sort_order'   => 5,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'code'         => 'SERVER_RANGE_2',
                'name'         => 'Profesional',
                'min_quantity' => 21,
                'max_quantity' => 100,
                'price_1yr'    => 110000.00,
                'price_2yr'    => 220000.00,
                'is_active'    => true,
                'sort_order'   => 6,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'code'         => 'SERVER_RANGE_3',
                'name'         => 'Enterprise',
                'min_quantity' => 101,
                'max_quantity' => 1000,
                'price_1yr'    => 101000.00,
                'price_2yr'    => 202000.00,
                'is_active'    => true,
                'sort_order'   => 7,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],

        ]);
    }
}
