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
        Schema::create('email_communications', function (Blueprint $table) {
            $table->id();
            $table->string('sender');
            $table->string('from_address');
            $table->timestamp('sent_at')->nullable();
            $table->string('recipient');
            $table->string('subject');
            $table->longText('body');
            $table->boolean('opened')->default(false);
            $table->integer('click_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_communications');
    }
};
