<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_audit_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_audit_id')->constrained()->cascadeOnDelete();
            $table->uuid('visit_id');
            $table->unsignedInteger('active_seconds')->default(0);
            $table->unsignedTinyInteger('scroll_percent')->default(0);
            foreach (['review_opened', 'review_dismissed', 'email_started', 'email_submit_attempted', 'email_submitted', 'booking_clicked', 'calendar_opened'] as $event) {
                $table->timestamp($event.'_at')->nullable();
            }
            $table->timestamps();
            $table->unique(['website_audit_id', 'visit_id']);
        });
        Schema::table('website_audits', function (Blueprint $table): void {
            $table->string('customer_goal', 30)->nullable();
            $table->string('lead_call_booking_uid')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_audit_visits');
        Schema::table('website_audits', function (Blueprint $table): void {
            $table->dropColumn(['customer_goal', 'lead_call_booking_uid']);
        });
    }
};
