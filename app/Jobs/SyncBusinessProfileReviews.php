<?php

namespace App\Jobs;

use App\Models\BusinessProfileConnection;
use App\Services\BusinessProfileClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncBusinessProfileReviews implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 3;

    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public BusinessProfileConnection $profile) {}

    public function uniqueId(): string
    {
        return (string) $this->profile->id;
    }

    /**
     * Execute the job.
     */
    public function handle(BusinessProfileClient $client): void
    {
        $client->syncReviews($this->profile);
    }
}
