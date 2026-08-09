<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3);
            $table->string('quote_currency', 3);
            $table->decimal('rate', 18, 6);
            $table->date('rate_date');
            $table->string('source', 100)->nullable();
            $table->timestamps();

            $table->unique(['base_currency', 'quote_currency', 'rate_date'], 'exchange_rates_unique_per_day');
            $table->index('rate_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
