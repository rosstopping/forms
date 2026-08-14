<?php

namespace App\Console\Commands;

use App\Mail\ContentSuggestionReminder;
use App\Models\ContentPlan;
use App\Models\SearchOpportunity;
use App\Models\SeoOpportunity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('content:send-suggestion-reminders')]
#[Description('Email content suggestions 24 hours before an empty weekly content queue runs')]
class SendContentSuggestionReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sent = 0;
        ContentPlan::query()->where('enabled', true)->with(['website.repository', 'creator'])->each(function (ContentPlan $plan) use (&$sent): void {
            $now = now($plan->timezone);
            $daysUntil = ($plan->weekday - $now->dayOfWeek + 7) % 7;
            $scheduledFor = $now->copy()->addDays($daysUntil)->setTime($plan->hour, 0);
            if ($scheduledFor->lessThanOrEqualTo($now)) {
                $scheduledFor->addWeek();
            }
            if ($scheduledFor->copy()->subDay()->format('Y-m-d-H') !== $now->format('Y-m-d-H') || $plan->suggestion_reminder_sent_for?->equalTo($scheduledFor)) {
                return;
            }
            if (! $plan->website->repository || ! $plan->creator?->email || $plan->website->contentRequests()->whereNull('picked_up_at')->exists()) {
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

        $this->info("Sent {$sent} content suggestion reminder(s).");

        return self::SUCCESS;
    }
}
