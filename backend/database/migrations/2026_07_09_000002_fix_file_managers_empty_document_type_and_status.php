<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Corrige registros en file_managers con document_type o status vacíos,
     * basándose en el archivo y contexto disponible.
     */
    public function up(): void
    {
        // ── Llenar document_type vacío para archivos P7B ──────────────────────────
        // Si extension_file = 'p7b' y document_type está vacío → P7B_CERTIFICATE
        DB::table('file_managers')
            ->where('extension_file', 'p7b')
            ->where(function ($query) {
                $query->whereNull('document_type')
                      ->orWhere('document_type', '');
            })
            ->update(['document_type' => 'P7B_CERTIFICATE']);

        // ── Llenar status vacío para archivos de llave privada ──────────────────
        // Si file_name = 'private_key_reference' y status está vacío → COMPLETED
        DB::table('file_managers')
            ->where('file_name', 'private_key_reference')
            ->where(function ($query) {
                $query->whereNull('status')
                      ->orWhere('status', '');
            })
            ->update(['status' => 'COMPLETED']);

        // ── Llenar status vacío para archivos ZIP de certificados ────────────────
        // Si extension_file = 'zip' y status está vacío → COMPLETED
        DB::table('file_managers')
            ->where('extension_file', 'zip')
            ->where(function ($query) {
                $query->whereNull('status')
                      ->orWhere('status', '');
            })
            ->update(['status' => 'COMPLETED']);

        // ── Llenar document_type vacío para archivos ZIP ──────────────────────────
        // Si extension_file = 'zip' y document_type está vacío → CERTIFICATE
        DB::table('file_managers')
            ->where('extension_file', 'zip')
            ->where(function ($query) {
                $query->whereNull('document_type')
                      ->orWhere('document_type', '');
            })
            ->update(['document_type' => 'CERTIFICATE']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertir: los datos están ya corregidos en producción
        // Si es necesario deshacer, hacerlo manualmente
    }
};
