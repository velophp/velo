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
        Schema::create('auth_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id');
            $table->foreignId('collection_id');
            $table->foreignId('record_id');

            $table->string('token_hash', 64)->unique();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->string('device_name')->nullable();
            $table->string('ip_address')->nullable()->index();

            $table->timestamps();

            $table->index(['project_id', 'record_id']);
            $table->index(['token_hash', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_sessions');
    }
};
