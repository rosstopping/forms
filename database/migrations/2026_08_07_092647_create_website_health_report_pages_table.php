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
        Schema::create('website_health_report_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_health_report_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->char('url_hash', 64);
            $table->unsignedSmallInteger('depth')->default(0);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedSmallInteger('h1_count')->default(0);
            $table->text('canonical_url')->nullable();
            $table->boolean('is_indexable')->default(true);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('internal_links_count')->default(0);
            $table->unsignedInteger('images_count')->default(0);
            $table->unsignedInteger('missing_alt_count')->default(0);
            $table->json('checks')->nullable();
            $table->timestamps();

            $table->unique(['website_health_report_id', 'url_hash'], 'health_report_page_url_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_health_report_pages');
    }
};
