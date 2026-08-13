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
        Schema::create('seo_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained(indexName: 'seo_competitors_website_fk')->cascadeOnDelete();
            $table->foreignId('seo_snapshot_id')->constrained(indexName: 'seo_competitors_snapshot_fk')->cascadeOnDelete();
            $table->string('domain');
            $table->unsignedInteger('common_keywords')->default(0);
            $table->unsignedInteger('organic_keywords')->nullable();
            $table->decimal('estimated_traffic', 14, 4)->nullable();
            $table->decimal('competition_level', 10, 6)->nullable();
            $table->timestamps();

            $table->unique(['seo_snapshot_id', 'domain'], 'seo_competitors_snapshot_domain_unique');
            $table->index(['website_id', 'common_keywords'], 'seo_competitors_website_common_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_competitors');
    }
};
