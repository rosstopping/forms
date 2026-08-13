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
        Schema::table('seo_opportunities', function (Blueprint $table) {
            $table->dropUnique('seo_opportunities_content_request_unique');
            $table->dropForeign('seo_opportunities_content_request_fk');
            $table->dropColumn('content_request_id');
        });
    }
};
