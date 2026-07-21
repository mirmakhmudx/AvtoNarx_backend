<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_listings', function (Blueprint $table) {
            $table->renameColumn('external_url', 'canonical_url');
            $table->renameColumn('raw_brand_name', 'brand_raw');
            $table->renameColumn('raw_model_name', 'model_raw');
            $table->renameColumn('price_amount_uzs', 'price_uzs');
            $table->renameColumn('listed_at', 'source_published_at');
        });

        Schema::table('market_listings', function (Blueprint $table) {
            $table->unsignedBigInteger('exchange_rate_id')->nullable()->after('price_uzs');
            $table->string('city', 120)->nullable()->after('region');
            $table->string('seller_type', 20)->default('unknown')->after('condition');
            $table->decimal('normalization_confidence', 5, 4)->nullable()->after('normalization_status');
            $table->timestamp('first_seen_at')->nullable()->after('source_published_at');
            $table->smallInteger('missing_runs')->default(0)->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('market_listings', function (Blueprint $table) {
            $table->dropColumn(array(
                'exchange_rate_id',
                'city',
                'seller_type',
                'normalization_confidence',
                'first_seen_at',
                'missing_runs',
            ));
        });

        Schema::table('market_listings', function (Blueprint $table) {
            $table->renameColumn('canonical_url', 'external_url');
            $table->renameColumn('brand_raw', 'raw_brand_name');
            $table->renameColumn('model_raw', 'raw_model_name');
            $table->renameColumn('price_uzs', 'price_amount_uzs');
            $table->renameColumn('source_published_at', 'listed_at');
        });
    }
};
