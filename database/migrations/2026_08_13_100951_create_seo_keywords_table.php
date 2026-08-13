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
        Schema::create('seo_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained(indexName: 'seo_keywords_website_fk')->cascadeOnDelete();
            $table->foreignId('seo_snapshot_id')->constrained(indexName: 'seo_keywords_snapshot_fk')->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->text('keyword');
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('previous_position')->nullable();
            $table->text('ranking_url')->nullable();
            $table->unsignedInteger('search_volume')->nullable();
            $table->decimal('cpc', 12, 4)->nullable();
            $table->decimal('competition', 6, 5)->nullable();
            $table->string('competition_level')->nullable();
            $table->string('search_intent')->nullable();
            $table->decimal('estimated_traffic', 14, 4)->nullable();
            $table->unsignedTinyInteger('keyword_difficulty')->nullable();
            $table->unsignedInteger('location_code');
            $table->string('language_code', 10);
            $table->timestamps();

            $table->unique(['seo_snapshot_id', 'fingerprint'], 'seo_keywords_snapshot_fingerprint_unique');
            $table->index(['seo_snapshot_id', 'position'], 'seo_keywords_snapshot_position_index');
            $table->index(['website_id', 'search_intent'], 'seo_keywords_website_intent_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_keywords');
    }
};
