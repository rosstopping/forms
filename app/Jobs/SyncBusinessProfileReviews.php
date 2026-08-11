<?php

namespace App\Jobs;

use App\Models\BusinessProfileConnection;
use App\Models\BusinessProfileReview;
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

    public function __construct(public BusinessProfileConnection $connection) {}

    public function uniqueId(): string
    {
        return (string) $this->connection->id;
    }

    /**
     * Execute the job.
     */
    public function handle(BusinessProfileClient $client): void
    {
        $client->syncReviews($this->connection);
        $this->connection->reviews()->where('reply_status', BusinessProfileReview::STATUS_UNANSWERED)->each(function (BusinessProfileReview $review): void {
            $review->update(['reply_status' => BusinessProfileReview::STATUS_GENERATING, 'error' => null]);
            GenerateBusinessProfileReviewReply::dispatch($review);
        });
    }
}
