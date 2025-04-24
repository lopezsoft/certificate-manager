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
        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('email_communication_id');
            $table->unsignedBigInteger('file_manager_id');
            $table->timestamps();
            $table->foreign('email_communication_id')->references('id')->on('email_communications')->onDelete('cascade');
            $table->foreign('file_manager_id')->references('id')->on('file_managers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
    }
};
