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
        Schema::table('content_generations', function (Blueprint $table) {
            $table->foreignId('seo_target_keyword_id')->nullable()->after('website_repository_id')->constrained()->nullOnDelete();
            $table->json('target_keyword_context')->nullable()->after('competitor_context');
        });
        Schema::table('external_api_usages', function (Blueprint $table) {
            $table->foreignId('seo_target_keyword_id')->nullable()->after('seo_snapshot_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('external_api_usages', fn (Blueprint $table) => $table->dropConstrainedForeignId('seo_target_keyword_id'));
        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seo_target_keyword_id');
            $table->dropColumn('target_keyword_context');
        });
    }
};
