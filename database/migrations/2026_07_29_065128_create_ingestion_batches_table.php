<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingestion_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('parser_client_id')->constrained('parser_clients')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('dataset', 30);
            $table->string('mode', 20);
            $table->uuid('idempotency_key');
            $table->string('parser_version', 50)->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('received_at');
            $table->string('status', 20)->default('received');
            $table->integer('items_total')->default(0);
            $table->integer('items_accepted')->default(0);
            $table->integer('items_rejected')->default(0);
            $table->string('payload_checksum', 64)->nullable();
            $table->jsonb('error_summary')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['parser_client_id', 'idempotency_key'], 'ingestion_batches_client_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestion_batches');
    }
};
