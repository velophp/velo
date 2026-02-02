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
        Schema::create('record_indexes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->index();
            $table->string('record_id')->index();
            $table->string('field')->index();

            $table->string('value_string')->nullable();
            $table->double('value_number')->nullable();
            $table->timestamp('value_datetime')->nullable();

            $table->timestamps();

            $table->index(['collection_id', 'field', 'value_string']);
            $table->index(['collection_id', 'field', 'value_number']);
            $table->index(['collection_id', 'field', 'value_datetime']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_indexes');
    }
};
