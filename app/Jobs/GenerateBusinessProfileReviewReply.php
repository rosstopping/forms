<?php

namespace App\Jobs;

use App\Ai\Agents\BusinessProfileReviewResponder;
use App\Models\BusinessProfileReview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateBusinessProfileReviewReply implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 3;

    public function __construct(public BusinessProfileReview $review) {}

    /**
     * Execute the job.
     */
    public function handle(BusinessProfileReviewResponder $responder): void
    {
        try {
            $connection = $this->review->connection;
            $response = $responder->prompt(json_encode(['business' => $connection->location_title ?: $connection->website->name, 'reviewer' => $this->review->reviewer_name, 'rating' => $this->review->star_rating, 'review' => $this->review->comment, 'brand_guidance' => $connection->brand_guidance], JSON_THROW_ON_ERROR));
            $this->review->update(['suggested_reply' => $response['reply'], 'reply_status' => BusinessProfileReview::STATUS_PENDING_APPROVAL, 'error' => null]);
        } catch (Throwable $exception) {
            $this->review->update(['reply_status' => BusinessProfileReview::STATUS_FAILED, 'error' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
