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
        Schema::create('seo_referring_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained(indexName: 'seo_ref_domains_website_fk')->cascadeOnDelete();
            $table->foreignId('seo_snapshot_id')->constrained(indexName: 'seo_ref_domains_snapshot_fk')->cascadeOnDelete();
            $table->string('domain');
            $table->unsignedSmallInteger('domain_rank')->nullable();
            $table->unsignedBigInteger('backlinks_count')->default(0);
            $table->timestamp('first_seen')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();

            $table->unique(['seo_snapshot_id', 'domain'], 'seo_ref_domains_snapshot_domain_unique');
            $table->index(['website_id', 'domain_rank'], 'seo_ref_domains_website_rank_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_referring_domains');
    }
};
