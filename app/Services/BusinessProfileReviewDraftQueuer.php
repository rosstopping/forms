<?php

namespace App\Services;

use App\Jobs\GenerateBusinessProfileReviewReply;
use App\Models\BusinessProfileConnection;
use App\Models\BusinessProfileReview;
use Illuminate\Support\Facades\DB;

class BusinessProfileReviewDraftQueuer
{
    public function queue(BusinessProfileConnection $connection, bool $includeFailed = false): int
    {
        if (blank($connection->location_name)) {
            return 0;
        }
        $statuses = [BusinessProfileReview::STATUS_UNANSWERED];
        if ($includeFailed) {
            $statuses[] = BusinessProfileReview::STATUS_FAILED;
        }
        $queued = 0;
        $connection->reviews()->whereIn('reply_status', $statuses)->each(function (BusinessProfileReview $review) use (&$queued, $statuses): void {
            DB::transaction(function () use ($review, &$queued, $statuses): void {
                $claimed = BusinessProfileReview::query()->whereKey($review->id)->whereIn('reply_status', $statuses)
                    ->update(['reply_status' => BusinessProfileReview::STATUS_GENERATING, 'error' => null]);
                if ($claimed) {
                    GenerateBusinessProfileReviewReply::dispatch($review->fresh())->afterCommit();
                    $queued++;
                }
            });
        });

        return $queued;
    }
}
