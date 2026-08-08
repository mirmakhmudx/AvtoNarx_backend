<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_price_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('model_id')->constrained('car_models')->cascadeOnDelete();
            $table->smallInteger('year')->nullable();
            $table->string('region', 100)->nullable();

            $table->integer('sample_size');
            $table->bigInteger('median_price_uzs');
            $table->bigInteger('mean_price_uzs');
            $table->bigInteger('min_price_uzs');
            $table->bigInteger('max_price_uzs');
            $table->bigInteger('p25_price_uzs')->nullable();
            $table->bigInteger('p75_price_uzs')->nullable();

            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['brand_id', 'model_id', 'year', 'region'], 'market_price_stats_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_price_statistics');
    }
};
