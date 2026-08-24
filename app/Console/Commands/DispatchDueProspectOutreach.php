<?php

namespace App\Console\Commands;

use App\Enums\ProspectAutomationStatus;
use App\Jobs\EvaluateProspectOutreach;
use App\Models\ProspectOutreachState;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('outreach:dispatch-due')]
#[Description('Dispatch lifecycle evaluation for prospects with a due outreach action')]
class DispatchDueProspectOutreach extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dispatched = 0;

        ProspectOutreachState::query()
            ->where('automation_status', ProspectAutomationStatus::Active)
            ->whereNotNull('next_action_at')
            ->where('next_action_at', '<=', now())
            ->select(['id', 'prospect_id'])
            ->chunkById(200, function ($states) use (&$dispatched): void {
                foreach ($states as $state) {
                    EvaluateProspectOutreach::dispatch($state->prospect_id);
                    $dispatched++;
                }
            });

        $this->info($dispatched.' due prospect outreach evaluation(s) dispatched.');

        return self::SUCCESS;
    }
}
