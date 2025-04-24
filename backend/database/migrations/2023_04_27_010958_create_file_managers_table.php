<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFileManagersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('file_managers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index('uuid');
            $table->unsignedBigInteger('user_id');
            $table->string('file_name', 120)->fulltext();
            $table->string('file_path', 400)->nullable()->default(null);
            $table->string('url', 600)->nullable()->default(null);
            $table->string('extension_file', 10);
            $table->string('mime_type', 120)->nullable()->default(null);
            $table->string('file_size', 30)->nullable()->default(null);
            $table->dateTime('last_modified')->nullable()->default(null);
            $table->smallInteger('state')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('file_managers');
    }
}
