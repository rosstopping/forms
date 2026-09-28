<?php

namespace App\Console\Commands;

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectLifecycleState;
use App\Models\Prospect;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('outreach:cool-inactive')]
#[Description('Return hot and warm prospects to cold after more than 14 days without interaction')]
class CoolInactiveProspects extends Command
{
    public function handle(): int
    {
        $cutoff = now()->startOfSecond()->subDays(14);
        $cooled = 0;

        Prospect::query()->whereIn('lead_temperature', ['hot', 'warm'])
            ->select('id')->chunkById(200, function ($prospects) use ($cutoff, &$cooled): void {
                foreach ($prospects as $candidate) {
                    DB::transaction(function () use ($candidate, $cutoff, &$cooled): void {
                        $prospect = Prospect::query()->lockForUpdate()->find($candidate->id);
                        if (! $prospect || ! in_array($prospect->lead_temperature, ['hot', 'warm'], true)) {
                            return;
                        }
                        $state = $prospect->outreachState()->lockForUpdate()->first();
                        $lastInteraction = $state?->last_engagement_at ?? $prospect->created_at;
                        if (! $lastInteraction || ! $lastInteraction->lt($cutoff)) {
                            return;
                        }

                        if ($state) {
                            $attributes = [
                                'temperature_override' => null,
                                'next_action_at' => null,
                                'manual_follow_up_required_at' => null,
                                'manual_follow_up_reason' => null,
                            ];
                            if ($state->automation_status === ProspectAutomationStatus::Active) {
                                $attributes['automation_status'] = ProspectAutomationStatus::Paused;
                            }
                            if (in_array($state->lifecycle_state, [ProspectLifecycleState::Hot, ProspectLifecycleState::Warm, ProspectLifecycleState::NeedsPersonalisedVideo], true)) {
                                $attributes['lifecycle_state'] = ProspectLifecycleState::Cold;
                            }
                            $state->update($attributes);
                        }
                        $prospect->update(['lead_temperature' => 'cold', 'next_follow_up_at' => null]);
                        $prospect->recordActivity('lead_temperature_cooled', 'Lead returned to cold after more than 14 days without interaction. Outreach has not been restarted.');
                        $cooled++;
                    });
                }
            });

        $this->info($cooled.' inactive prospect(s) returned to cold.');

        return self::SUCCESS;
    }
}
