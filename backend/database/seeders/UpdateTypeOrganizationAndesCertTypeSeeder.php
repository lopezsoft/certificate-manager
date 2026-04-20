<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de homologación — Solo ejecuta UPDATE sobre registros existentes.
 * Agrega el valor de andes_cert_type a la nueva columna de tipo_organización.
 *
 * NUNCA elimina, trunca ni modifica columnas preexistentes.
 *
 * Ejecución:
 * php artisan db:seed --class=UpdateTypeOrganizationAndesCertTypeSeeder
 */
class UpdateTypeOrganizationAndesCertTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Persona Jurídica (id=1) → ANDES tipoCert 10 (FE P.Jurídica)
        DB::table('type_organization')
            ->where('id', 1)
            ->update(['andes_cert_type' => 10]);

        // Persona Natural (id=2) → ANDES tipoCert 11 (FE P.Natural)
        DB::table('type_organization')
            ->where('id', 2)
            ->update(['andes_cert_type' => 11]);

        $this->command->info('UpdateTypeOrganizationAndesCertTypeSeeder ejecutado correctamente.');
    }
}

