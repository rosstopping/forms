<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_wins', function (Blueprint $table): void {
            $table->string('category', 20)->default('win');
            $table->foreignId('seo_impact_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['website_id', 'category', 'confirmed_at'], 'seo_signals_site_category_date');
        });
    }

    public function down(): void
    {
        Schema::table('seo_wins', function (Blueprint $table): void {
            $table->dropIndex('seo_signals_site_category_date');
            $table->dropConstrainedForeignId('seo_impact_id');
            $table->dropColumn('category');
        });
    }
};
