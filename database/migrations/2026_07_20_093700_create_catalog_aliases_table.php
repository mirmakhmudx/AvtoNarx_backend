<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_aliases', function (Blueprint $table) {
            $table->id();
            $table->enum('entity_type', ['brand', 'model']);
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->string('alias', 180);
            $table->string('normalized_alias', 180);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->unique(['source_id', 'normalized_alias', 'entity_type'], 'catalog_aliases_unique_per_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_aliases');
    }
};
