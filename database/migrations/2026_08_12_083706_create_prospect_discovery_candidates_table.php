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
        Schema::create('prospect_discovery_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospect_discovery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_key', 100);
            $table->string('business_name');
            $table->string('website_url')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->json('source_data')->nullable();
            $table->string('status', 30)->default('new');
            $table->timestamps();

            $table->unique(['prospect_discovery_id', 'source_key'], 'pdc_source_unique');
            $table->index(['prospect_discovery_id', 'status'], 'pdc_discovery_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospect_discovery_candidates');
    }
};
