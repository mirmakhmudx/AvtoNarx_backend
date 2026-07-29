<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RunParserSourceJob/RunParserTargetsChunkJob manba haqiqatan bloklaganini
     * (403/429/CAPTCHA) aniqlasa, shu maydonga "tinch turish" muddatini yozadi.
     * Shu muddat o'tmaguncha, o'sha manba uchun navbatga qo'yilgan boshqa
     * partiya (chunk) job'lari ham ishni boshlamasdan darhol chiqib ketadi —
     * bloklangan manbani qayta-qayta "urib" ko'rmaymiz.
     */
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->timestamp('blocked_until')->nullable()->after('trust_level');
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn('blocked_until');
        });
    }
};
