<?php

namespace App\Services;

use App\Enums\ProspectOutreachStopReason;
use App\Models\Prospect;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProspectUnsubscriber
{
    public function __construct(private ProspectLifecycleManager $lifecycleManager) {}

    public function unsubscribe(Prospect $prospect): void
    {
        Cache::lock('prospect-outreach-evaluate-'.$prospect->getKey(), 60)->block(5, function () use ($prospect): void {
            Cache::lock('prospect-outreach-send-'.$prospect->getKey(), 60)->block(5, function () use ($prospect): void {
                DB::transaction(function () use ($prospect): void {
                    $prospect = Prospect::query()->lockForUpdate()->findOrFail($prospect->id);
                    if ($prospect->unsubscribed_at !== null) {
                        return;
                    }

                    $prospect->forceFill([
                        'unsubscribed_at' => now(),
                        'suppressed_at' => $prospect->suppressed_at ?? now(),
                        'approved_at' => null,
                        'approved_by' => null,
                    ])->save();
                    $state = $this->lifecycleManager->stop($prospect, reason: ProspectOutreachStopReason::Unsubscribed);
                    $state->update([
                        'future_opportunity_at' => null,
                        'manual_follow_up_required_at' => null,
                        'manual_follow_up_reason' => null,
                    ]);
                    $prospect->outreachDeliveries()->whereNull('sent_at')
                        ->whereIn('status', ['pending', 'scheduled', 'failed'])
                        ->update(['status' => 'cancelled', 'scheduled_at' => null, 'failure_reason' => 'Recipient unsubscribed.']);
                    $prospect->recordActivity('unsubscribed', 'Recipient confirmed unsubscribe. All future outreach stopped and pending emails cancelled.');
                });
            });
        });
    }
}
