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
        if (! Schema::hasTable('seo_opportunities') || Schema::hasColumn('seo_opportunities', 'content_request_id')) {
            return;
        }

        Schema::table('seo_opportunities', function (Blueprint $table) {
            $table->foreignId('content_request_id')
                ->nullable()
                ->after('website_id')
                ->constrained(indexName: 'seo_opportunities_content_request_fk')
                ->nullOnDelete();
            $table->unique('content_request_id', 'seo_opportunities_content_request_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration repairs production schema drift and must not remove a column owned by the original migration.
    }
};
