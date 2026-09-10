<?php

namespace App\Console\Commands;

use App\Mail\ContentSuggestionReminder;
use App\Models\ContentPlan;
use App\Models\SearchOpportunity;
use App\Models\SeoOpportunity;
use App\Services\ContentSchedule;
use App\Services\WebsiteMailRecipients;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

#[Signature('content:send-suggestion-reminders')]
#[Description('Email content suggestions the day before an empty scheduled content queue runs')]
class SendContentSuggestionReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WebsiteMailRecipients $recipients, ContentSchedule $schedule): int
    {
        $sent = 0;
        ContentPlan::query()->where('enabled', true)->each(function (ContentPlan $candidate) use ($recipients, $schedule, &$sent): void {
            DB::transaction(function () use ($candidate, $recipients, $schedule, &$sent): void {
                $plan = ContentPlan::query()->with(['website.owner', 'website.members', 'website.repository', 'creator.githubAuthorization'])->lockForUpdate()->findOrFail($candidate->id);
                $scheduledFor = $schedule->reminderRunAt($plan);
                if (! $scheduledFor || $plan->suggestion_reminder_sent_for?->equalTo($scheduledFor)) {
                    return;
                }
                if (! $plan->website->repository
                    || ! $plan->creator?->email
                    || $recipients->isViewer($plan->website, $plan->creator->email)
                    || $plan->website->contentRequests()->whereNull('picked_up_at')->exists()) {
                    return;
                }
                $search = $plan->website->searchOpportunities()->where('status', SearchOpportunity::STATUS_OPEN)->latest('priority_score')->limit(3)->get();
                $seo = $plan->website->seoOpportunities()->where('status', SeoOpportunity::STATUS_OPEN)->with('keyword')->latest('priority_score')->limit(3)->get();
                if ($search->isEmpty() && $seo->isEmpty()) {
                    return;
                }
                Mail::to($plan->creator)->send(new ContentSuggestionReminder($plan, $search, $seo));
                $plan->update(['suggestion_reminder_sent_for' => $scheduledFor->utc()]);
                $sent++;
            });
        });

        $this->info("Sent {$sent} content suggestion reminder(s).");

        return self::SUCCESS;
    }
}
