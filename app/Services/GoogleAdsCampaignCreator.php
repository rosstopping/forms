<?php

namespace App\Services;

use App\Models\GoogleAdsCampaignDraft;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class GoogleAdsCampaignCreator
{
    public function __construct(protected GoogleAdsClient $client) {}

    public function create(GoogleAdsCampaignDraft $draft): GoogleAdsCampaignDraft
    {
        return Cache::lock('google-ads-draft-'.$draft->id, 60)->block(5, function () use ($draft): GoogleAdsCampaignDraft {
            $draft->refresh();
            if ($draft->status !== GoogleAdsCampaignDraft::STATUS_PENDING) {
                return $draft;
            }

            $connection = $draft->connection;
            if ($connection->customer_id !== $draft->customer_id) {
                throw new RuntimeException('The connected Ads account changed. Create a new draft for the current account.');
            }
            $operations = $this->operations($draft);
            $this->client->mutate($connection, $operations, validateOnly: true);
            $draft->update(['status' => GoogleAdsCampaignDraft::STATUS_UNCERTAIN]);
            try {
                $response = $this->client->mutate($connection, $operations);
            } catch (RequestException $exception) {
                if (in_array($exception->response->status(), [400, 401, 403, 404, 422], true)) {
                    $draft->update(['status' => GoogleAdsCampaignDraft::STATUS_FAILED]);
                }

                throw $exception;
            }
            $resourceName = data_get($response, 'mutateOperationResponses.1.campaignResult.resourceName');
            if (! is_string($resourceName) || $resourceName === '') {
                throw new RuntimeException('Google Ads did not confirm the campaign resource name. Inspect the account before trying again.');
            }
            $draft->update(['status' => GoogleAdsCampaignDraft::STATUS_CREATED, 'campaign_resource_name' => $resourceName]);

            return $draft->fresh();
        });
    }

    /** @return list<array<string, mixed>> */
    public function operations(GoogleAdsCampaignDraft $draft): array
    {
        if (! $draft->max_cpc_micros || $draft->max_cpc_micros < 20000) {
            throw new RuntimeException('Set a max CPC bid before creating this campaign.');
        }

        $customer = $draft->customer_id;
        $budget = "customers/{$customer}/campaignBudgets/-1";
        $campaign = "customers/{$customer}/campaigns/-2";
        $adGroup = "customers/{$customer}/adGroups/-3";
        $operations = [
            ['campaignBudgetOperation' => ['create' => [
                'resourceName' => $budget,
                'name' => $draft->name.' budget',
                'amountMicros' => (string) $draft->daily_budget_micros,
                'deliveryMethod' => 'STANDARD',
                'explicitlyShared' => false,
            ]]],
            ['campaignOperation' => ['create' => [
                'resourceName' => $campaign,
                'name' => $draft->name,
                'status' => 'PAUSED',
                'advertisingChannelType' => 'SEARCH',
                'campaignBudget' => $budget,
                'manualCpc' => new \stdClass,
                'networkSettings' => [
                    'targetGoogleSearch' => true,
                    'targetSearchNetwork' => false,
                    'targetPartnerSearchNetwork' => false,
                    'targetContentNetwork' => false,
                ],
                'geoTargetTypeSetting' => ['positiveGeoTargetType' => 'PRESENCE'],
                'containsEuPoliticalAdvertising' => 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING',
            ]]],
            ['campaignCriterionOperation' => ['create' => [
                'campaign' => $campaign,
                'proximity' => [
                    'address' => ['cityName' => $draft->city_name, 'countryCode' => $draft->country_code],
                    'radius' => $draft->radius_miles,
                    'radiusUnits' => 'MILES',
                ],
            ]]],
            ['adGroupOperation' => ['create' => [
                'resourceName' => $adGroup,
                'name' => 'Search opportunities',
                'campaign' => $campaign,
                'cpcBidMicros' => (string) $draft->max_cpc_micros,
                'type' => 'SEARCH_STANDARD',
                'status' => 'ENABLED',
            ]]],
        ];
        foreach ($draft->keywords as $keyword) {
            $operations[] = ['adGroupCriterionOperation' => ['create' => [
                'adGroup' => $adGroup,
                'status' => 'ENABLED',
                'keyword' => ['text' => $keyword, 'matchType' => 'EXACT'],
            ]]];
        }
        $operations[] = ['adGroupAdOperation' => ['create' => [
            'adGroup' => $adGroup,
            'status' => 'ENABLED',
            'ad' => [
                'finalUrls' => [$draft->final_url],
                'responsiveSearchAd' => [
                    'headlines' => array_map(fn (string $text): array => ['text' => $text], $draft->headlines),
                    'descriptions' => array_map(fn (string $text): array => ['text' => $text], $draft->descriptions),
                ],
            ],
        ]]];

        return $operations;
    }
}
