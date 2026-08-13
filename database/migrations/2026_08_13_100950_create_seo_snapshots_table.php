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
        Schema::create('seo_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained(indexName: 'seo_snapshots_website_fk')->cascadeOnDelete();
            $table->string('provider')->default('dataforseo');
            $table->string('domain');
            $table->unsignedInteger('location_code');
            $table->string('language_code', 10);
            $table->string('status')->default('pending');
            $table->unsignedInteger('organic_keywords')->nullable();
            $table->decimal('estimated_organic_traffic', 14, 4)->nullable();
            $table->unsignedInteger('top_3_keywords')->nullable();
            $table->unsignedInteger('top_10_keywords')->nullable();
            $table->unsignedInteger('top_20_keywords')->nullable();
            $table->unsignedInteger('top_100_keywords')->nullable();
            $table->unsignedBigInteger('backlinks')->nullable();
            $table->unsignedInteger('referring_domains')->nullable();
            $table->unsignedInteger('referring_ips')->nullable();
            $table->unsignedInteger('referring_subnets')->nullable();
            $table->unsignedBigInteger('broken_backlinks')->nullable();
            $table->unsignedSmallInteger('domain_rank')->nullable();
            $table->date('snapshot_date');
            $table->json('metadata')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'provider', 'snapshot_date'], 'seo_snapshots_website_provider_date_index');
            $table->index(['website_id', 'status', 'created_at'], 'seo_snapshots_website_status_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_snapshots');
    }
};
