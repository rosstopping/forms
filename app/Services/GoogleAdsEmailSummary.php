<?php

namespace App\Services;

use App\Models\Website;
use App\Support\MembershipPlan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use RuntimeException;

class GoogleAdsEmailSummary
{
    public function __construct(private GoogleAdsClient $client) {}

    /** @return array{campaigns: int, impressions: int, clicks: int, cost_micros: int, conversions: float, currency: string, start: Carbon, end: Carbon}|null */
    public function forPeriod(Website $website, Carbon $start, Carbon $end): ?array
    {
        $connection = $website->googleAdsConnection;
        if (! $website->owner?->hasMembershipFeature(MembershipPlan::FEATURE_COMPLETE)
            || ! $connection?->customer_id || ! $connection->currency_code) {
            return null;
        }

        try {
            $performance = $this->client->enabledCampaignPerformance($connection, $start, $end);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return null;
        }

        return $performance === null ? null : [
            ...$performance,
            'currency' => $connection->currency_code,
            'start' => $start,
            'end' => $end,
        ];
    }
}
