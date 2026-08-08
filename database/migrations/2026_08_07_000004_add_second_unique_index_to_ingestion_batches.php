<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TZ 7.6: ingestion_batches uchun IKKINCHI unique indeks — (parser_client_id, id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingestion_batches', function (Blueprint $table) {
            $table->unique(array('parser_client_id', 'id'), 'ingestion_batches_client_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ingestion_batches', function (Blueprint $table) {
            $table->dropUnique('ingestion_batches_client_id_unique');
        });
    }
};
