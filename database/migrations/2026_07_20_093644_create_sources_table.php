<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->enum('type', ['marketplace', 'manufacturer', 'dealer', 'manual']);
            $table->string('base_url', 500);
            $table->boolean('is_active')->default(true);
            $table->boolean('ingestion_enabled')->default(false);
            $table->enum('trust_level', ['official', 'verified', 'unverified'])->default('unverified');
            $table->jsonb('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
