<?php

namespace App\Console\Commands;

use App\Jobs\SendLeadFollowUpReminder;
use App\Models\FormSubmissionFollowUpReminder;
use Illuminate\Console\Command;

class DispatchDueLeadFollowUpReminders extends Command
{
    protected $signature = 'leads:dispatch-follow-up-reminders';

    protected $description = 'Queue due lead follow-up reminder emails';

    public function handle(): int
    {
        FormSubmissionFollowUpReminder::query()
            ->where('status', 'pending')->where('due_at', '<=', now())
            ->chunkById(100, function ($reminders): void {
                foreach ($reminders as $reminder) {
                    SendLeadFollowUpReminder::dispatch($reminder->id);
                }
            });

        $this->info('Due lead follow-up reminders dispatched.');

        return self::SUCCESS;
    }
}
