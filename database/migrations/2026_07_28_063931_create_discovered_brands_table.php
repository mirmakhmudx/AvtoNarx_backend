<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovered_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('name', 190);
            $table->string('slug', 190);
            $table->string('discovered_url', 700);
            $table->timestamp('last_models_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'slug'], 'discovered_brands_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovered_brands');
    }
};
