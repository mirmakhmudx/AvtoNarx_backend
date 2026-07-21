<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('model_id')->constrained('car_models')->cascadeOnDelete();

            $table->string('trim_name', 120)->nullable();
            $table->smallInteger('year')->nullable();

            $table->bigInteger('price_amount');
            $table->string('currency', 3)->default('UZS');
            $table->bigInteger('price_amount_uzs')->nullable();

            $table->string('status', 20)->default('pending');
            $table->string('external_id', 190)->nullable();

            $table->timestamp('effective_from')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->index(['brand_id', 'model_id', 'status']);
            $table->unique(['source_id', 'model_id', 'trim_name'], 'official_offers_unique_trim');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_offers');
    }
};
