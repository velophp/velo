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
        Schema::create('realtime_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->foreignId('record_id')->nullable()->constrained('records')->cascadeOnDelete();
            $table->string('socket_id')->nullable();
            $table->uuid('channel_name')->unique();
            $table->string('filter')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamp('last_seen_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('collection_id');
            $table->index('channel_name');
            $table->index('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('realtime_connections');
    }
};
