<?php

namespace App\Jobs;

use App\Models\ProspectOutreachDelivery;
use App\Services\ProspectPersonalisedVideo;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

class SendScheduledProspectPersonalisedVideo implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $deliveryId, public CarbonImmutable $scheduledFor) {}

    public function handle(ProspectPersonalisedVideo $personalisedVideo): void
    {
        $delivery = ProspectOutreachDelivery::find($this->deliveryId);

        if (! $delivery || ! $delivery->scheduled_at?->equalTo($this->scheduledFor)) {
            return;
        }

        try {
            $personalisedVideo->sendScheduled($delivery);
        } catch (LogicException $exception) {
            $delivery->update(['status' => 'cancelled', 'scheduled_at' => null, 'failure_reason' => $exception->getMessage()]);
            $delivery->prospect->recordActivity('personalised_video_cancelled', 'Scheduled personalised video was not sent: '.$exception->getMessage());
        }
    }

    public function uniqueId(): string
    {
        return $this->deliveryId.':'.$this->scheduledFor->getTimestamp();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Scheduled personalised video delivery failed.', [
            'delivery_id' => $this->deliveryId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
