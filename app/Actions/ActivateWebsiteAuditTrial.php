<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAudit;
use App\Models\WebsiteDomain;
use App\Support\MembershipPlan;
use Illuminate\Support\Facades\DB;

class ActivateWebsiteAuditTrial
{
    /** @param array{name?: string, password?: string} $profile */
    public function handle(WebsiteAudit $audit, array $profile = []): User
    {
        return DB::transaction(function () use ($audit, $profile): User {
            $audit = WebsiteAudit::query()->lockForUpdate()->findOrFail($audit->id);

            if ($audit->user_id) {
                return $audit->user()->firstOrFail();
            }

            $user = User::query()->where('email', $audit->email)->first();

            if (! $user) {
                $user = User::query()->create([
                    'name' => $profile['name'],
                    'email' => $audit->email,
                    'password' => $profile['password'],
                    'role' => User::ROLE_USER,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            if (! $user->hasActiveMembership()) {
                $trialEndsAt = now()->addDays(14);
                $user->forceFill([
                    'membership_tier' => MembershipPlan::GROWTH,
                    'membership_status' => 'trialing',
                    'membership_current_period_end' => $trialEndsAt,
                    'onboarding_status' => 'trial_active',
                    'onboarding_trial_ends_at' => $trialEndsAt,
                ])->save();
            }

            $website = Website::query()->create([
                'user_id' => $user->id,
                'name' => $audit->domain,
                'is_active' => true,
                'auto_discovered' => false,
                'email_enabled' => false,
                'webhook_enabled' => false,
                'health_reports_enabled' => true,
                'seo_weekly_snapshots_enabled' => false,
                'pixel_enabled' => false,
                'wordpress_enabled' => false,
            ]);
            $website->members()->attach($user->id, ['role' => Website::MEMBER_ROLE_MANAGER]);
            $website->domains()->create([
                'domain' => $audit->domain,
                'is_primary' => true,
                'ownership_status' => WebsiteDomain::OWNERSHIP_PENDING,
            ]);

            $audit->update([
                'user_id' => $user->id,
                'website_id' => $website->id,
                'claimed_at' => now(),
            ]);

            return $user;
        });
    }
}
