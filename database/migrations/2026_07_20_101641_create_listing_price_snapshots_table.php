<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_listing_id')->constrained('market_listings')->cascadeOnDelete();
            $table->bigInteger('price_amount');
            $table->string('currency', 3);
            $table->bigInteger('price_amount_uzs')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['market_listing_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_price_snapshots');
    }
};
