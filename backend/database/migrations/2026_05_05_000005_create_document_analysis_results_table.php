<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla para persistir resultados de análisis IA de documentos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('certificate_request_id');
            $table->foreign('certificate_request_id')
                  ->references('id')->on('certificate_requests')
                  ->cascadeOnDelete();
            $table->unsignedBigInteger('file_manager_id')->nullable();
            $table->string('provider', 30)->comment('GEMINI, OPENAI, MOCK');
            $table->string('analysis_type', 50)->default('general')
                  ->comment('general, rut, cedula, chamber_commerce');
            $table->text('ocr_text')->nullable()->comment('Texto raw extraído por OCR');
            $table->string('ocr_provider', 30)->nullable()->comment('GOOGLE_VISION, TEXTRACT, MOCK');
            $table->decimal('ocr_confidence', 5, 2)->nullable();
            $table->json('ai_response')->nullable()->comment('Respuesta estructurada del análisis IA');
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->decimal('completeness_score', 5, 2)->nullable();
            $table->json('extracted_data')->nullable()->comment('Datos clave extraídos');
            $table->json('validation_result')->nullable()->comment('Resultado de validación');
            $table->decimal('processing_time', 8, 3)->nullable()->comment('Tiempo en segundos');
            $table->string('status', 20)->default('COMPLETED')
                  ->comment('PENDING, PROCESSING, COMPLETED, FAILED');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();

            $table->index('certificate_request_id');
            $table->index('provider');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_analysis_results');
    }
};
