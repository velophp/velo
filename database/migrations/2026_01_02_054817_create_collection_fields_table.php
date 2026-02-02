<?php

use App\Domain\Field\Enums\FieldType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('collection_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->integer('order')->nullable();
            $table->string('name');
            $table->json('options');
            $table->string('type')->default(FieldType::Text);
            $table->boolean('required')->default(false);
            $table->boolean('unique')->default(false);
            $table->boolean('locked')->default(false);
            $table->boolean('indexed')->default(false);
            $table->boolean('hidden')->default(false);
            $table->timestamps();

            $table->unique(['collection_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_fields');
    }
};
