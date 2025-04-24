<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suppressed_emails', function (Blueprint $table) {
            $table->id(); // Clave primaria simple

            // El correo electrónico suprimido. Debe ser único.
            $table->string('email')->unique();

            // Razón principal por la que se suprimió
            // Ej: 'Bounce', 'Complaint'
            $table->string('reason_type');

            // Subtipo o detalle adicional de la razón (opcional pero útil)
            // Ej: 'Permanent', 'General', 'MailboxFull' (aunque aquí guardarías los permanentes),
            // o el tipo específico de queja si está disponible.
            $table->string('reason_subtype')->nullable();

            // El código de diagnóstico completo (mensaje de error) si es un rebote (opcional)
            $table->text('diagnostic_code')->nullable();

            // Timestamp de cuándo se añadió a esta lista
            $table->timestamp('suppressed_at');

            // Fuente de la supresión (opcional, para rastreo)
            // Ej: 'SES_Notification', 'Manual_Import', 'API'
            $table->string('source')->nullable();

            // Timestamps estándar de Laravel (created_at, updated_at)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppressed_emails');
    }
};
