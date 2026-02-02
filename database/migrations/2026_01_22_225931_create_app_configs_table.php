<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->index()->constrained()->cascadeOnDelete();
            $table->string('app_name');
            $table->string('app_url')->default('http://localhost');
            $table->json('trusted_proxies')->nullable();
            $table->integer('rate_limits')->nullable();
            $table->text('email_settings')->nullable();
            $table->text('storage_settings')->nullable();
            $table->timestamps();

            $table->unique(['project_id']); // Ensure one config per project
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_configs');
    }
};
