<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TZ 8.5: heartbeat parser holatini yuboradi — versiya, navbat hajmi, xost
 * hash'i, oxirgi run vaqti. Vaqt ustunlari timestamptz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parser_clients', function (Blueprint $table) {
            $table->string('hostname_hash', 64)->nullable()->after('parser_version');
            $table->unsignedInteger('queue_size')->nullable()->after('hostname_hash');
            $table->timestampTz('last_run_at')->nullable()->after('queue_size');
            $table->timestampTz('last_heartbeat_at')->nullable()->after('last_run_at');
        });
    }

    public function down(): void
    {
        Schema::table('parser_clients', function (Blueprint $table) {
            $table->dropColumn(array('hostname_hash', 'queue_size', 'last_run_at', 'last_heartbeat_at'));
        });
    }
};
