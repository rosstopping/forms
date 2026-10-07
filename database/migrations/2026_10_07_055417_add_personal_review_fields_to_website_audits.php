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
        Schema::table('website_audits', function (Blueprint $table) {
            $table->timestamp('personal_review_requested_at')->nullable()->index();
            $table->timestamp('personal_review_due_at')->nullable();
            $table->timestamp('personal_review_queued_at')->nullable();
            $table->json('personal_review')->nullable();
            $table->timestamp('marketing_consent_at')->nullable();
            $table->timestamp('marketing_consent_withdrawn_at')->nullable();
            $table->string('marketing_consent_version')->nullable();
            $table->timestamp('lead_replied_at')->nullable();
            $table->timestamp('lead_call_booked_at')->nullable();
            $table->timestamp('lead_converted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_audits', function (Blueprint $table) {
            $table->dropColumn(['personal_review_requested_at', 'personal_review_due_at', 'personal_review_queued_at', 'personal_review', 'marketing_consent_at', 'marketing_consent_withdrawn_at', 'marketing_consent_version', 'lead_replied_at', 'lead_call_booked_at', 'lead_converted_at']);
        });
    }
};
