<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_price_statistics', function (Blueprint $table) {
            $table->renameColumn('region', 'region_code');
        });

        Schema::table('market_price_statistics', function (Blueprint $table) {
            $table->string('currency', 3)->default('UZS')->after('region_code');
            $table->integer('excluded_count')->default(0)->after('sample_size');
            $table->timestamp('period_from')->nullable()->after('calculated_at');
            $table->timestamp('period_to')->nullable()->after('period_from');
            $table->string('method_version', 30)->default('v1')->after('period_to');
        });
    }

    public function down(): void
    {
        Schema::table('market_price_statistics', function (Blueprint $table) {
            $table->dropColumn(['currency', 'excluded_count', 'period_from', 'period_to', 'method_version']);
        });

        Schema::table('market_price_statistics', function (Blueprint $table) {
            $table->renameColumn('region_code', 'region');
        });
    }
};
