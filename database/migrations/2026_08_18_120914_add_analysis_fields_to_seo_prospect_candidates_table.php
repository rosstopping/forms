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
        Schema::table('seo_prospect_candidates', function (Blueprint $table) {
            $table->json('audit_findings')->nullable()->after('audit_score');
            $table->json('contact_details')->nullable()->after('audit_findings');
            $table->text('analysis_error')->nullable()->after('qualification_status');
            $table->timestamp('analyzed_at')->nullable()->after('analysis_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_prospect_candidates', function (Blueprint $table) {
            $table->dropColumn(['audit_findings', 'contact_details', 'analysis_error', 'analyzed_at']);
        });
    }
};
