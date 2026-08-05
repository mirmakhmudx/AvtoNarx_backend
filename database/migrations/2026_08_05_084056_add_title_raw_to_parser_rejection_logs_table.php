<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nega kerak: title_model_mismatch sababi bilan rad etilgan yozuvlarda
 * OLX kartochkasining ASL sarlavha matni saqlanmagan edi — shuning uchun
 * "nega bu rad etildi?" degan savolga faqat TAXMIN qilib javob berish
 * mumkin edi. Endi asl matn to'g'ridan-to'g'ri admin panelda ko'rinadi,
 * shunda solishtirish qoidasini ANIQ misollar asosida sozlash mumkin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parser_rejection_logs', function (Blueprint $table) {
            $table->string('title_raw', 500)->nullable()->after('model_raw');
        });
    }

    public function down(): void
    {
        Schema::table('parser_rejection_logs', function (Blueprint $table) {
            $table->dropColumn('title_raw');
        });
    }
};
