<?php

namespace App\Services;

use App\Jobs\GenerateBusinessProfilePost;
use App\Models\BusinessProfileConnection;
use App\Models\BusinessProfilePost;
use Illuminate\Support\Facades\DB;

class BusinessProfilePostQueuer
{
    public function draftNextScheduled(BusinessProfileConnection $connection): bool
    {
        return DB::transaction(function () use ($connection): bool {
            $connection = BusinessProfileConnection::query()->lockForUpdate()->findOrFail($connection->id);
            $localNow = now($connection->timezone);
            if (! $connection->weekly_posts_enabled || blank($connection->location_name)
                || $localNow->dayOfWeek !== $connection->post_weekday || $localNow->hour !== $connection->post_hour
                || $connection->last_post_scheduled_at?->gte($localNow->copy()->startOfDay()->utc())) {
                return false;
            }
            $post = $connection->posts()->where('status', BusinessProfilePost::STATUS_QUEUED)->oldest('id')->first();
            if (! $post || ! $this->draft($post)) {
                return false;
            }
            $connection->update(['last_post_scheduled_at' => now()]);

            return true;
        });
    }

    public function draft(BusinessProfilePost $post): bool
    {
        return DB::transaction(function () use ($post): bool {
            $claimed = BusinessProfilePost::query()->whereKey($post->id)
                ->whereIn('status', [BusinessProfilePost::STATUS_QUEUED, BusinessProfilePost::STATUS_FAILED])
                ->update(['status' => BusinessProfilePost::STATUS_GENERATING, 'error' => null]);
            if (! $claimed) {
                return false;
            }
            GenerateBusinessProfilePost::dispatch($post->fresh())->afterCommit();

            return true;
        });
    }
}
