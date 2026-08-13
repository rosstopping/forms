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
        Schema::create('seo_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained(indexName: 'seo_opportunities_website_fk')->cascadeOnDelete();
            $table->foreignId('seo_snapshot_id')->constrained(indexName: 'seo_opportunities_snapshot_fk')->cascadeOnDelete();
            $table->foreignId('seo_keyword_id')->nullable()->constrained(indexName: 'seo_opportunities_keyword_fk')->nullOnDelete();
            $table->string('fingerprint', 64);
            $table->string('type');
            $table->string('status')->default('open');
            $table->string('title');
            $table->text('summary');
            $table->text('recommendation');
            $table->json('metrics')->nullable();
            $table->decimal('priority_score', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['seo_snapshot_id', 'fingerprint'], 'seo_opportunities_snapshot_fingerprint_unique');
            $table->index(['website_id', 'status', 'priority_score'], 'seo_opportunities_website_status_priority_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_opportunities');
    }
};
