<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('websites')
            ->join('users', 'users.id', '=', 'websites.user_id')
            ->whereNull('websites.service_package')
            ->whereNull('websites.service_status')
            ->select([
                'websites.id', 'websites.is_active', 'users.membership_tier',
                'users.admin_membership_tier', 'users.admin_membership_expires_at',
                'users.membership_status', 'users.membership_current_period_end',
            ])
            ->eachById(function (stdClass $website) use ($now): void {
                $hasOverride = $website->admin_membership_tier !== null
                    && ($website->admin_membership_expires_at === null
                        || Carbon::parse($website->admin_membership_expires_at)->greaterThan($now));
                $package = $hasOverride ? $website->admin_membership_tier : $website->membership_tier;

                if (! in_array($package, ['essential', 'growth', 'complete'], true)) {
                    return;
                }

                $hasActiveBilling = $website->membership_status === 'active'
                    || ($website->membership_status === 'trialing'
                        && $website->membership_current_period_end !== null
                        && Carbon::parse($website->membership_current_period_end)->greaterThan($now));
                $endsAt = $hasOverride
                    ? $website->admin_membership_expires_at
                    : ($website->membership_status === 'trialing' ? $website->membership_current_period_end : null);

                DB::table('websites')->where('id', $website->id)->update([
                    'service_package' => $package,
                    'service_status' => $website->is_active && ($hasOverride || $hasActiveBilling) ? 'active' : 'paused',
                    'service_ends_at' => $endsAt,
                ]);
            }, column: 'websites.id', alias: 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /** Backfilled settings may have been edited; schema rollback removes the fields. */
    }
};
