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
        Schema::table('seo_prospect_searches', function (Blueprint $table) {
            $table->foreignId('rerun_of_id')->nullable()->after('user_id')->constrained('seo_prospect_searches')->nullOnDelete();
            $table->decimal('estimated_api_cost', 12, 6)->default(0)->after('api_cost');
            $table->unsignedSmallInteger('fresh_keyword_count')->default(0)->after('estimated_api_cost');
            $table->unsignedSmallInteger('cached_keyword_count')->default(0)->after('fresh_keyword_count');
            $table->json('serp_freshness')->nullable()->after('cached_keyword_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_prospect_searches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rerun_of_id');
            $table->dropColumn(['estimated_api_cost', 'fresh_keyword_count', 'cached_keyword_count', 'serp_freshness']);
        });
    }
};
