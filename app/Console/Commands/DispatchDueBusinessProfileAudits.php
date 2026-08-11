<?php

namespace App\Console\Commands;

use App\Jobs\AuditBusinessProfile;
use App\Jobs\GenerateBusinessProfilePost;
use App\Jobs\SyncBusinessProfileReviews;
use App\Models\BusinessProfileAudit;
use App\Models\BusinessProfileConnection;
use App\Models\BusinessProfilePost;
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
    public function handle(): int
    {
        $dispatched = 0;
        BusinessProfileConnection::query()->whereHas('website', fn ($query) => $query->where('is_active', true))->with('website')->each(function (BusinessProfileConnection $connection) use (&$dispatched): void {
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
            $localNow = now($connection->timezone);
            $postDue = $connection->weekly_posts_enabled && $localNow->dayOfWeek === $connection->post_weekday && $localNow->hour === $connection->post_hour;
            $alreadyGenerated = $connection->posts()->where('created_at', '>=', $localNow->copy()->startOfDay()->utc())->exists();
            if ($postDue && ! $alreadyGenerated) {
                GenerateBusinessProfilePost::dispatch($connection->posts()->create(['status' => BusinessProfilePost::STATUS_GENERATING]));
                $dispatched++;
            }
        });
        $this->info("Dispatched {$dispatched} Business Profile task(s).");

        return self::SUCCESS;
    }
}
