<?php

namespace App\Services;

use App\Models\BusinessProfileConnection;
use App\Models\BusinessProfileReview;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class BusinessProfileClient
{
    public function __construct(protected BusinessProfileOAuthClient $oauth) {}

    /** @return array<int, array<string, mixed>> */
    public function accounts(BusinessProfileConnection $connection): array
    {
        return $this->request($connection, 'account')->get('accounts')->throw()->json('accounts', []);
    }

    /** @return array<int, array<string, mixed>> */
    public function locations(BusinessProfileConnection $connection, string $accountName): array
    {
        return $this->request($connection, 'information')->get($accountName.'/locations', ['readMask' => 'name,title,storefrontAddress,phoneNumbers,categories,regularHours,websiteUri,profile'])->throw()->json('locations', []);
    }

    /** @return array<string, mixed> */
    public function location(BusinessProfileConnection $connection): array
    {
        $this->ensureLocation($connection);

        return $this->request($connection, 'information')->get($connection->location_name, ['readMask' => 'name,title,storefrontAddress,phoneNumbers,categories,regularHours,specialHours,websiteUri,profile,metadata'])->throw()->json();
    }

    /** @return array<int, array<string, mixed>> */
    public function reviews(BusinessProfileConnection $connection): array
    {
        $this->ensureLocation($connection);

        $reviews = [];
        $pageToken = null;
        do {
            $response = $this->request($connection, 'v4')->get($this->v4LocationName($connection).'/reviews', array_filter([
                'pageSize' => 50, 'orderBy' => 'updateTime desc', 'pageToken' => $pageToken,
            ]))->throw()->json();
            $reviews = [...$reviews, ...($response['reviews'] ?? [])];
            $pageToken = $response['nextPageToken'] ?? null;
        } while (filled($pageToken));

        return $reviews;
    }

    /** @param array<string, mixed> $values */
    public function updateLocation(BusinessProfileConnection $connection, string $fieldMask, array $values): void
    {
        $this->ensureLocation($connection);
        $this->request($connection, 'information')->patch($connection->location_name, ['updateMask' => $fieldMask, ...$values])->throw();
    }

    /** @return array<string, mixed> */
    public function createPost(BusinessProfileConnection $connection, string $summary, ?string $callToActionType, ?string $url): array
    {
        $this->ensureLocation($connection);
        $payload = ['languageCode' => 'en-GB', 'summary' => $summary, 'topicType' => 'STANDARD'];
        if ($callToActionType && $url) {
            $payload['callToAction'] = ['actionType' => $callToActionType, 'url' => $url];
        }

        return $this->request($connection, 'v4')->post($this->v4LocationName($connection).'/localPosts', $payload)->throw()->json();
    }

    public function replyToReview(BusinessProfileConnection $connection, string $reviewName, string $comment): void
    {
        $this->request($connection, 'v4')->put($reviewName.'/reply', ['comment' => $comment])->throw();
    }

    /** @return array<string, mixed> */
    public function weeklyPerformance(BusinessProfileConnection $connection, Carbon $start, Carbon $end): array
    {
        $this->ensureLocation($connection);
        $metrics = ['BUSINESS_IMPRESSIONS_DESKTOP_MAPS', 'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH', 'BUSINESS_IMPRESSIONS_MOBILE_MAPS', 'BUSINESS_IMPRESSIONS_MOBILE_SEARCH', 'WEBSITE_CLICKS', 'CALL_CLICKS', 'BUSINESS_DIRECTION_REQUESTS'];
        $query = collect($metrics)->map(fn (string $metric): string => 'dailyMetrics='.$metric)->implode('&');
        foreach (['startDate' => $start, 'endDate' => $end] as $key => $date) {
            foreach (['year', 'month', 'day'] as $part) {
                $query .= '&dailyRange.'.$key.'.'.$part.'='.$date->$part;
            }
        }
        $location = 'locations/'.basename($connection->location_name);

        return $this->request($connection, 'performance')->timeout(10)->retry(1)
            ->get($location.':fetchMultiDailyMetricsTimeSeries?'.$query)->throw()->json();
    }

    /** @return array{count: ?int, rating: ?float} */
    public function reviewSummary(BusinessProfileConnection $connection): array
    {
        $response = $this->request($connection, 'v4')->timeout(10)->retry(1)
            ->get($this->v4LocationName($connection).'/reviews', ['pageSize' => 1])->throw()->json();

        return ['count' => isset($response['totalReviewCount']) ? (int) $response['totalReviewCount'] : null,
            'rating' => isset($response['averageRating']) ? (float) $response['averageRating'] : null];
    }

    public function syncReviews(BusinessProfileConnection $connection): void
    {
        foreach ($this->reviews($connection) as $review) {
            DB::transaction(function () use ($connection, $review): void {
                $stored = $connection->reviews()->where('google_review_name', $review['name'])->lockForUpdate()->first()
                    ?? $connection->reviews()->make(['google_review_name' => $review['name'], 'reply_status' => BusinessProfileReview::STATUS_UNANSWERED]);
                $stored->fill([
                    'reviewer_name' => data_get($review, 'reviewer.displayName'),
                    'star_rating' => $this->rating((string) ($review['starRating'] ?? 'ONE')),
                    'comment' => $review['comment'] ?? null,
                    'reviewed_at' => filled($review['createTime'] ?? null) ? Carbon::parse($review['createTime']) : null,
                ]);
                if (filled($reply = data_get($review, 'reviewReply.comment'))) {
                    $stored->fill(['google_reply' => $reply, 'reply_status' => BusinessProfileReview::STATUS_REPLIED, 'error' => null]);
                }
                $stored->save();
            });
        }
        $connection->update(['last_synced_at' => now()]);
        app(BusinessProfileReviewDraftQueuer::class)->queue($connection);
    }

    protected function request(BusinessProfileConnection $connection, string $api): PendingRequest
    {
        $url = match ($api) {
            'performance' => config('services.google.business_profile_performance_url'), 'account' => config('services.google.business_profile_account_url'), 'information' => config('services.google.business_profile_information_url'), default => config('services.google.business_profile_v4_url')
        };

        return Http::baseUrl((string) $url)
            ->acceptJson()
            ->withToken($this->oauth->accessToken($connection))
            ->connectTimeout(5)
            ->timeout(30)
            ->retry([500, 1500], function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            }, throw: false);
    }

    protected function ensureLocation(BusinessProfileConnection $connection): void
    {
        if (! $connection->location_name) {
            throw new RuntimeException('Select a Google Business Profile location first.');
        }
    }

    protected function v4LocationName(BusinessProfileConnection $connection): string
    {
        $this->ensureLocation($connection);

        return str_starts_with((string) $connection->location_name, 'accounts/')
            ? $connection->location_name
            : $connection->account_name.'/'.ltrim((string) $connection->location_name, '/');
    }

    protected function rating(string $rating): int
    {
        return ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5][$rating] ?? 1;
    }
}
