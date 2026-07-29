<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingestion_item_errors', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->foreign('batch_id')->references('id')->on('ingestion_batches')->cascadeOnDelete();
            $table->integer('item_index');
            $table->string('external_id', 255)->nullable();
            $table->string('code', 80);
            $table->string('field', 120)->nullable();
            $table->text('message');
            $table->jsonb('payload_excerpt')->nullable();
            $table->timestamps();

            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestion_item_errors');
    }
};
