<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_opportunities', function (Blueprint $table) {
            $table->index('content_request_id', 'seo_opportunities_content_request_index');
        });

        Schema::table('seo_opportunities', function (Blueprint $table) {
            $table->dropUnique('seo_opportunities_content_request_unique');
        });
    }

    public function down(): void
    {
        Schema::table('seo_opportunities', function (Blueprint $table) {
            $table->unique('content_request_id', 'seo_opportunities_content_request_unique');
        });

        Schema::table('seo_opportunities', function (Blueprint $table) {
            $table->dropIndex('seo_opportunities_content_request_index');
        });
    }
};
