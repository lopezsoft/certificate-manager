<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de homologación — Solo ejecuta UPDATE sobre registros existentes.
 * Agrega el valor de andes_code a la nueva columna agregada por migración 000001.
 *
 * NUNCA elimina, trunca ni modifica columnas preexistentes.
 *
 * Ejecución:
 * php artisan db:seed --class=UpdateIdentityDocumentsAndesCodeSeeder
 */
class UpdateIdentityDocumentsAndesCodeSeeder extends Seeder
{
    public function run(): void
    {
        // Cédula de Ciudadanía (code=13) → ANDES TipoDoc 1
        DB::table('identity_documents')
            ->where('code', '13')
            ->update(['andes_code' => 1]);

        // Cédula de Extranjería (code=22) → ANDES TipoDoc 3
        DB::table('identity_documents')
            ->where('code', '22')
            ->update(['andes_code' => 3]);

        // NIT (code=31) → ANDES TipoDoc 2 (solo como TipoDocEnt, nunca como TipoDoc persona)
        DB::table('identity_documents')
            ->where('code', '31')
            ->update(['andes_code' => 2]);

        // Pasaporte → ANDES TipoDoc 6
        // Si no existe, insertarlo; si existe, actualizar su andes_code
        $existePasaporte = DB::table('identity_documents')
            ->whereRaw("LOWER(document_name) LIKE '%pasaporte%'")
            ->exists();

        if ($existePasaporte) {
            DB::table('identity_documents')
                ->whereRaw("LOWER(document_name) LIKE '%pasaporte%'")
                ->update(['andes_code' => 6]);
        } else {
            DB::table('identity_documents')->insert([
                'code'          => '41',
                'document_name' => 'Pasaporte',
                'andes_code'    => 6,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $this->command->info('Pasaporte insertado con andes_code=6');
        }

        $this->command->info('UpdateIdentityDocumentsAndesCodeSeeder ejecutado correctamente.');
    }
}

