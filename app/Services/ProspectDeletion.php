<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProspectDeletion
{
    public function __construct(private ProspectLifecycleManager $lifecycleManager) {}

    public function delete(Prospect $prospect, User $actor): bool
    {
        return DB::transaction(function () use ($prospect, $actor): bool {
            $prospect = Prospect::query()->lockForUpdate()->findOrFail($prospect->id);
            $this->lifecycleManager->stop($prospect, $actor);
            $prospect->outreachDeliveries()->whereNull('sent_at')
                ->whereIn('status', ['pending', 'scheduled', 'failed'])
                ->update(['status' => 'cancelled', 'scheduled_at' => null, 'failure_reason' => 'Prospect deleted.']);
            $prospect->recordActivity('deleted', 'Prospect deleted and retained in the Deleted filter. Pending outreach cancelled.', $actor);

            return (bool) $prospect->delete();
        });
    }
}
