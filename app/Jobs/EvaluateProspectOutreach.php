<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Services\ProspectOutreachSequence;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvaluateProspectOutreach implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $prospectId) {}

    public function handle(ProspectOutreachSequence $sequence): void
    {
        $prospect = Prospect::find($this->prospectId);

        if ($prospect) {
            $sequence->evaluate($prospect);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->prospectId;
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Prospect outreach evaluation failed.', [
            'prospect_id' => $this->prospectId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
