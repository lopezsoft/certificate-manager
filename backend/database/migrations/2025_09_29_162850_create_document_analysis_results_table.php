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
        Schema::create('document_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('certificate_request_id');
            $table->json('analysis_results'); // Complete analysis results
            $table->json('validation_summary'); // Validation summary for quick access
            $table->json('validation_errors')->nullable(); // Validation errors
            $table->json('extracted_data')->nullable(); // Extracted data from all documents
            $table->boolean('overall_valid')->default(false);
            $table->boolean('rut_found')->default(false);
            $table->enum('person_type', ['natural', 'juridica'])->nullable();
            $table->boolean('chamber_commerce_required')->default(false);
            $table->boolean('chamber_commerce_found')->default(false);
            $table->boolean('chamber_commerce_valid_date')->default(false);
            $table->boolean('cedula_found')->default(false);
            $table->boolean('cedula_complete')->default(false);
            $table->boolean('legal_representative_match')->default(false);
            $table->integer('documents_processed')->default(0);
            $table->decimal('processing_time', 8, 4)->nullable(); // seconds
            $table->timestamps();

            // Foreign key constraint (commented for now - table may not exist yet)
            // $table->foreign('certificate_request_id')->references('id')->on('certificate_requests')->onDelete('cascade');
            
            // Indexes for better performance
            $table->index('certificate_request_id');
            $table->index('overall_valid');
            $table->index('person_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_analysis_results');
    }
};
