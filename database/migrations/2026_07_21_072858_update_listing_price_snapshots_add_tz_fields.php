<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_price_snapshots', function (Blueprint $table) {
            $table->renameColumn('captured_at', 'observed_at');
        });

        Schema::table('listing_price_snapshots', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->after('price_amount_uzs');
        });
    }

    public function down(): void
    {
        Schema::table('listing_price_snapshots', function (Blueprint $table) {
            $table->dropColumn('content_hash');
        });

        Schema::table('listing_price_snapshots', function (Blueprint $table) {
            $table->renameColumn('observed_at', 'captured_at');
        });
    }
};
