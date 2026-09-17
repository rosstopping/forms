<?php

namespace App\Console\Commands;

use App\Jobs\AuditBusinessProfile;
use App\Jobs\SyncBusinessProfileReviews;
use App\Models\BusinessProfileAudit;
use App\Models\BusinessProfileConnection;
use App\Services\BusinessProfilePostQueuer;
use App\Support\MembershipPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business-profiles:dispatch-audits')]
#[Description('Dispatch due weekly Google Business Profile audits and post drafts')]
class DispatchDueBusinessProfileAudits extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BusinessProfilePostQueuer $posts): int
    {
        $dispatched = 0;
        BusinessProfileConnection::query()->whereHas('website', fn ($query) => $query->where('is_active', true))->with('website.owner')->each(function (BusinessProfileConnection $connection) use (&$dispatched, $posts): void {
            if (blank($connection->location_name) || ! $connection->website->owner?->hasMembershipFeature(MembershipPlan::FEATURE_COMPLETE)) {
                return;
            }
            $recentAudit = $connection->audits()->where(fn ($query) => $query
                ->whereIn('status', [BusinessProfileAudit::STATUS_PENDING, BusinessProfileAudit::STATUS_RUNNING])
                ->orWhere(fn ($query) => $query->where('status', BusinessProfileAudit::STATUS_COMPLETED)->where('completed_at', '>=', now()->subWeek())))->exists();
            if ($connection->weekly_audits_enabled && ! $recentAudit) {
                AuditBusinessProfile::dispatch($connection->audits()->create(['status' => BusinessProfileAudit::STATUS_PENDING]));
                $dispatched++;
            }
            if (! $connection->last_synced_at || $connection->last_synced_at->isBefore(now()->subHour())) {
                SyncBusinessProfileReviews::dispatch($connection);
                $dispatched++;
            }
            if ($posts->draftNextScheduled($connection)) {
                $dispatched++;
            }
        });
        $this->info("Dispatched {$dispatched} Business Profile task(s).");

        return self::SUCCESS;
    }
}
