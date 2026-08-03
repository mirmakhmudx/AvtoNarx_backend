<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bu jadval nima uchun kerak: ingestion_item_errors faqat HTTP ingestion API
 * orqali kelgan (ingestion_batches yozuvi mavjud bo'lgan) xatolarni saqlaydi.
 * Lekin ichki scraper (RunParserTargetsChunkJob -> OlxUzAdapter) hech qanday
 * batch yaratmasdan to'g'ridan-to'g'ri ListingIngestionService'ni chaqiradi.
 * ListingSanityChecker aniqlagan shubhali yozuvlar (masalan OLX'ning
 * "extended_search_no_results_last_resort" fallback natijalari yoki mashina
 * uchun aqlga sig'maydigan darajada past narx) shu yerga — batch'siz —
 * yoziladi, shunda ikkala yo'l (ichki scraper ham, tashqi HTTP ingestion
 * ham) bitta markazlashtirilgan jurnalga tushadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parser_rejection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->string('external_id', 255)->nullable();
            $table->string('canonical_url', 1000)->nullable();
            $table->string('brand_raw', 180)->nullable();
            $table->string('model_raw', 180)->nullable();
            $table->bigInteger('price_amount')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('code', 80);
            $table->text('message')->nullable();
            $table->timestamp('rejected_at');
            $table->timestamps();

            $table->index(['source_id', 'code']);
            $table->index('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parser_rejection_logs');
    }
};
