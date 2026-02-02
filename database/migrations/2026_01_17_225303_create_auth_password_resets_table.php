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
        Schema::create('auth_password_resets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->default(1);

            $table->string('email')->index();
            $table->string('token', 64)->unique();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();

            $table->string('device_name')->nullable();
            $table->string('ip_address')->nullable();

            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_password_resets');
    }
};
