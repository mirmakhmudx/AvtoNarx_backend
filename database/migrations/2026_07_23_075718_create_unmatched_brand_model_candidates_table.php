<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unmatched_brand_model_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('brand_name_raw', 190);
            $table->string('model_name_raw', 190);
            $table->string('discovered_url', 700);
            $table->string('status', 20)->default('pending'); // pending, resolved, ignored
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['source_id', 'brand_name_raw', 'model_name_raw'], 'unmatched_candidates_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unmatched_brand_model_candidates');
    }
};
