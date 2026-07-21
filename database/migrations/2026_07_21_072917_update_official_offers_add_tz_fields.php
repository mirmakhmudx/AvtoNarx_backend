<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_offers', function (Blueprint $table) {
            $table->renameColumn('price_amount_uzs', 'price_uzs');
            $table->renameColumn('status', 'publication_status');
            $table->renameColumn('effective_from', 'valid_from');
        });

        Schema::table('official_offers', function (Blueprint $table) {
            $table->string('source_url', 1000)->nullable()->after('price_uzs');
            $table->timestamp('valid_to')->nullable()->after('valid_from');
            $table->timestamp('observed_at')->nullable()->after('valid_to');
            $table->timestamp('verified_at')->nullable()->after('published_at');
            $table->unsignedBigInteger('verified_by')->nullable()->after('verified_at');
            $table->string('content_hash', 64)->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('official_offers', function (Blueprint $table) {
            $table->dropColumn(array('source_url', 'valid_to', 'observed_at', 'verified_at', 'verified_by', 'content_hash'));
        });

        Schema::table('official_offers', function (Blueprint $table) {
            $table->renameColumn('price_uzs', 'price_amount_uzs');
            $table->renameColumn('publication_status', 'status');
            $table->renameColumn('valid_from', 'effective_from');
        });
    }
};
