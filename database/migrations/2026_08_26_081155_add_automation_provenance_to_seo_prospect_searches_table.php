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
            $table->foreignId('prospecting_industry_profile_id')->nullable()->after('rerun_of_id')->constrained()->nullOnDelete();
            $table->foreignId('prospecting_location_id')->nullable()->after('prospecting_industry_profile_id')->constrained()->nullOnDelete();
            $table->boolean('automated')->default(false)->after('prospecting_location_id')->index();
            $table->string('automation_key', 64)->nullable()->after('automated')->unique();
            $table->unsignedTinyInteger('automatic_import_score')->nullable()->after('automation_key');
            $table->timestamp('automatic_import_dispatched_at')->nullable()->after('automatic_import_score');

            $table->index(['prospecting_industry_profile_id', 'prospecting_location_id'], 'seo_search_profile_location_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_prospect_searches', function (Blueprint $table) {
            $table->dropIndex('seo_search_profile_location_index');
            $table->dropUnique(['automation_key']);
            $table->dropIndex(['automated']);
            $table->dropConstrainedForeignId('prospecting_location_id');
            $table->dropConstrainedForeignId('prospecting_industry_profile_id');
            $table->dropColumn(['automated', 'automation_key', 'automatic_import_score', 'automatic_import_dispatched_at']);
        });
    }
};
