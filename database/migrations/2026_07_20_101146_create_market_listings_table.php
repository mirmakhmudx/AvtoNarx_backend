<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('external_id', 190);
            $table->string('external_url', 700);

            $table->string('raw_brand_name', 190)->nullable();
            $table->string('raw_model_name', 190)->nullable();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('car_models')->nullOnDelete();
            $table->string('normalization_status', 20)->default('pending');

            $table->smallInteger('year')->nullable();
            $table->bigInteger('price_amount');
            $table->string('currency', 3)->default('UZS');
            $table->bigInteger('price_amount_uzs')->nullable();

            $table->string('condition', 10)->default('used');
            $table->string('region', 100)->nullable();
            $table->string('status', 20)->default('active');

            $table->string('content_hash', 64);
            $table->timestamp('listed_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index(['brand_id', 'model_id', 'year']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_listings');
    }
};
