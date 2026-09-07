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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_call_booking_started_at')->nullable()->after('onboarding_trial_ends_at');
            $table->timestamp('onboarding_call_booked_at')->nullable()->after('onboarding_call_booking_started_at');
            $table->timestamp('onboarding_call_completed_at')->nullable()->after('onboarding_call_booked_at');
            $table->timestamp('onboarding_health_report_viewed_at')->nullable()->after('onboarding_call_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'onboarding_call_booking_started_at',
                'onboarding_call_booked_at',
                'onboarding_call_completed_at',
                'onboarding_health_report_viewed_at',
            ]);
        });
    }
};
