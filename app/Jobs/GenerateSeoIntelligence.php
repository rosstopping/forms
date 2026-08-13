<?php

namespace App\Jobs;

use App\Models\SeoSnapshot;
use App\Services\SeoIntelligence\SeoSnapshotService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateSeoIntelligence implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public int $timeout = 240;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public SeoSnapshot $snapshot) {}

    public function uniqueId(): string
    {
        return (string) $this->snapshot->website_id;
    }

    public function handle(SeoSnapshotService $snapshots): void
    {
        $snapshots->process($this->snapshot);
    }

    public function failed(?Throwable $exception): void
    {
        $this->snapshot->update([
            'status' => SeoSnapshot::STATUS_FAILED,
            'errors' => ['generation' => 'SEO intelligence could not be generated. Try again later.'],
            'completed_at' => now(),
        ]);
    }
}
