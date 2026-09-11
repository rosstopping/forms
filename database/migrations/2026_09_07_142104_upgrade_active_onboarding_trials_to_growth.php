<?php

use App\Support\MembershipPlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('onboarding_status', 'trial_active')
            ->where('membership_status', 'trialing')
            ->where('membership_tier', MembershipPlan::ESSENTIAL)
            ->where('membership_current_period_end', '>', now())
            ->update([
                'membership_tier' => MembershipPlan::GROWTH,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('onboarding_status', 'trial_active')
            ->where('membership_status', 'trialing')
            ->where('membership_tier', MembershipPlan::GROWTH)
            ->where('membership_current_period_end', '>', now())
            ->update([
                'membership_tier' => MembershipPlan::ESSENTIAL,
                'updated_at' => now(),
            ]);
    }
};
