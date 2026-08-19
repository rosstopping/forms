<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Services\ProspectOutreachSender;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use LogicException;

class SendScheduledProspectOutreach implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     */
    public function __construct(public int $prospectId, public CarbonImmutable $scheduledFor) {}

    /**
     * Execute the job.
     */
    public function handle(ProspectOutreachSender $sender): void
    {
        $prospect = Prospect::find($this->prospectId);

        if (! $prospect || ! $prospect->scheduled_send_at?->equalTo($this->scheduledFor)) {
            return;
        }

        try {
            $sender->send($prospect);
        } catch (LogicException $exception) {
            $prospect->update(['scheduled_send_at' => null]);
            $prospect->recordActivity('send_cancelled', 'Scheduled outreach was not sent: '.$exception->getMessage());
        }
    }
}
