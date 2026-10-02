<?php

namespace App\Services;

use App\Models\GoogleAdsConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleAdsClient
{
    public function __construct(protected GoogleAdsOAuthClient $oauth) {}

    /** @return list<string> */
    public function accessibleCustomerIds(GoogleAdsConnection $connection): array
    {
        $response = $this->request($connection)->get($this->url('customers:listAccessibleCustomers'))->throw()->json();

        return collect($response['resourceNames'] ?? [])
            ->filter(fn (mixed $name): bool => is_string($name) && preg_match('/^customers\/\d{10}$/', $name) === 1)
            ->map(fn (string $name): string => substr($name, 10))->values()->all();
    }

    /**
     * @return array{accounts: list<array{id: string, name: string, currency: string, login_customer_id: ?string, manager_name: ?string}>, unavailable_count: int}
     */
    public function availableAccounts(GoogleAdsConnection $connection): array
    {
        return Cache::remember(
            'google-ads-accounts:'.$connection->id.':'.hash('sha256', $connection->access_token),
            now()->addMinutes(5),
            fn (): array => $this->discoverAvailableAccounts($connection),
        );
    }

    /**
     * @return array{accounts: list<array{id: string, name: string, currency: string, login_customer_id: ?string, manager_name: ?string}>, unavailable_count: int}
     */
    protected function discoverAvailableAccounts(GoogleAdsConnection $connection): array
    {
        $accounts = [];
        $managers = [];
        $unavailableCount = 0;

        foreach ($this->accessibleCustomerIds($connection) as $customerId) {
            try {
                $customer = $this->customerSummary($connection, $customerId);
            } catch (ConnectionException|RequestException|RuntimeException) {
                $unavailableCount++;

                continue;
            }

            if ($customer['manager']) {
                $managers[$customerId] = $customer['name'];

                continue;
            }

            $accounts[$customerId] = [
                'id' => $customerId,
                'name' => $customer['name'],
                'currency' => $customer['currency'],
                'login_customer_id' => null,
                'manager_name' => null,
            ];
        }

        foreach ($managers as $managerId => $managerName) {
            $managerId = (string) $managerId;
            try {
                $response = $this->request($connection, $managerId)
                    ->post($this->url("customers/{$managerId}/googleAds:searchStream"), [
                        'query' => 'SELECT customer_client.id, customer_client.descriptive_name, customer_client.currency_code, customer_client.manager, customer_client.level, customer_client.status, customer_client.hidden FROM customer_client WHERE customer_client.level > 0 LIMIT 1000',
                    ])->throw()->json();
                if (! is_array($response)) {
                    throw new RuntimeException('Google Ads returned an invalid account list.');
                }
            } catch (ConnectionException|RequestException|RuntimeException) {
                $unavailableCount++;

                continue;
            }

            foreach ($response as $batch) {
                if (! is_array($batch)) {
                    continue;
                }
                foreach ($batch['results'] ?? [] as $result) {
                    $child = $result['customerClient'] ?? null;
                    $childId = (string) ($child['id'] ?? '');
                    if (preg_match('/^\d{10}$/', $childId) !== 1
                        || ($child['manager'] ?? false)
                        || ($child['hidden'] ?? false)
                        || ($child['status'] ?? '') !== 'ENABLED'
                        || isset($accounts[$childId])) {
                        continue;
                    }

                    $accounts[$childId] = [
                        'id' => $childId,
                        'name' => (string) (($child['descriptiveName'] ?? null) ?: $childId),
                        'currency' => (string) ($child['currencyCode'] ?? ''),
                        'login_customer_id' => $managerId,
                        'manager_name' => $managerName,
                    ];
                }
            }
        }

        $accounts = array_values($accounts);
        usort($accounts, fn (array $first, array $second): int => strnatcasecmp($first['name'], $second['name']));

        return ['accounts' => $accounts, 'unavailable_count' => $unavailableCount];
    }

    /** @return array{id: string, name: string, currency: string} */
    public function customer(GoogleAdsConnection $connection, string $customerId, ?string $loginCustomerId = null): array
    {
        $customer = $this->customerSummary($connection, $customerId, $loginCustomerId);
        if ($customer['manager']) {
            throw new RuntimeException('Choose a Google Ads client account, not a manager account.');
        }

        return [
            'id' => $customerId,
            'name' => $customer['name'],
            'currency' => $customer['currency'],
        ];
    }

    /** @return array{name: string, currency: string, manager: bool} */
    protected function customerSummary(GoogleAdsConnection $connection, string $customerId, ?string $loginCustomerId = null): array
    {
        $this->assertCustomerId($customerId);
        if ($loginCustomerId !== null) {
            $this->assertCustomerId($loginCustomerId);
        }
        $response = $this->request($connection, $loginCustomerId)
            ->post($this->url("customers/{$customerId}/googleAds:searchStream"), [
                'query' => 'SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.manager FROM customer LIMIT 1',
            ])->throw()->json();
        $customer = data_get($response, '0.results.0.customer');
        if (! is_array($customer)) {
            throw new RuntimeException('Google Ads did not return an account.');
        }

        return [
            'name' => (string) (($customer['descriptiveName'] ?? null) ?: $customerId),
            'currency' => (string) ($customer['currencyCode'] ?? ''),
            'manager' => (bool) ($customer['manager'] ?? false),
        ];
    }

    /** @param list<array<string, mixed>> $operations
     * @return array<string, mixed>
     */
    public function mutate(GoogleAdsConnection $connection, array $operations, bool $validateOnly = false): array
    {
        $customerId = (string) $connection->customer_id;
        $this->assertCustomerId($customerId);
        $response = $this->request($connection, $connection->login_customer_id)
            ->post($this->url("customers/{$customerId}/googleAds:mutate"), [
                'mutateOperations' => $operations,
                'partialFailure' => false,
                'validateOnly' => $validateOnly,
            ])->throw()->json();

        if (! is_array($response)) {
            throw new RuntimeException('Google Ads returned an invalid campaign response.');
        }

        return $response;
    }

    /** @return list<array{id: string, name: string, category: string, type: string, primary: bool}> */
    public function conversionActions(GoogleAdsConnection $connection): array
    {
        $customerId = (string) $connection->customer_id;
        $this->assertCustomerId($customerId);
        $response = $this->request($connection, $connection->login_customer_id)
            ->post($this->url("customers/{$customerId}/googleAds:searchStream"), [
                'query' => "SELECT conversion_action.id, conversion_action.name, conversion_action.category, conversion_action.type, conversion_action.primary_for_goal FROM conversion_action WHERE conversion_action.status = 'ENABLED' LIMIT 100",
            ])->throw()->json();

        return collect($response)
            ->flatMap(fn (mixed $batch): array => is_array($batch) ? ($batch['results'] ?? []) : [])
            ->map(fn (array $result): array => [
                'id' => (string) data_get($result, 'conversionAction.id', ''),
                'name' => (string) data_get($result, 'conversionAction.name', ''),
                'category' => (string) data_get($result, 'conversionAction.category', ''),
                'type' => (string) data_get($result, 'conversionAction.type', ''),
                'primary' => (bool) data_get($result, 'conversionAction.primaryForGoal', false),
            ])->filter(fn (array $action): bool => $action['name'] !== '')
            ->values()->all();
    }

    /** @return list<array{id: string, name: string, status: string}> */
    public function campaignsNamed(GoogleAdsConnection $connection, string $name): array
    {
        $customerId = (string) $connection->customer_id;
        $this->assertCustomerId($customerId);
        $escapedName = str_replace(['\\', "'", "\n", "\r"], ['\\\\', "\\'", '\\n', '\\r'], $name);
        $response = $this->request($connection, $connection->login_customer_id)
            ->post($this->url("customers/{$customerId}/googleAds:searchStream"), [
                'query' => "SELECT campaign.id, campaign.name, campaign.status FROM campaign WHERE campaign.name = '{$escapedName}' LIMIT 20",
            ])->throw()->json();

        if (! is_array($response)) {
            throw new RuntimeException('Google Ads returned an invalid campaign list.');
        }

        return collect($response)
            ->flatMap(fn (mixed $batch): array => is_array($batch) ? ($batch['results'] ?? []) : [])
            ->map(fn (array $result): array => [
                'id' => (string) data_get($result, 'campaign.id', ''),
                'name' => (string) data_get($result, 'campaign.name', ''),
                'status' => (string) data_get($result, 'campaign.status', ''),
            ])->filter(fn (array $campaign): bool => $campaign['id'] !== '' && $campaign['name'] === $name)
            ->values()->all();
    }

    /** @return list<array{id: string, resource_name: string, name: string, status: string, type: string, daily_budget_micros: int}> */
    public function campaigns(GoogleAdsConnection $connection): array
    {
        return $this->searchCampaigns($connection, "campaign.status != 'REMOVED' ORDER BY campaign.id DESC");
    }

    /** @return array<string, array{impressions: int, clicks: int, cost_micros: int, conversions: float}> */
    public function campaignPerformance(GoogleAdsConnection $connection): array
    {
        $rows = $this->searchRows($connection, "SELECT campaign.id, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions FROM campaign WHERE campaign.status != 'REMOVED' AND segments.date DURING LAST_30_DAYS");
        $performance = [];
        foreach ($rows as $row) {
            $id = (string) data_get($row, 'campaign.id', '');
            if (preg_match('/^[1-9]\d*$/', $id) !== 1) {
                continue;
            }
            $performance[$id] = [
                'impressions' => (int) data_get($row, 'metrics.impressions', 0),
                'clicks' => (int) data_get($row, 'metrics.clicks', 0),
                'cost_micros' => (int) data_get($row, 'metrics.costMicros', 0),
                'conversions' => (float) data_get($row, 'metrics.conversions', 0),
            ];
        }

        return $performance;
    }

    /** @return array{id: string, resource_name: string, name: string, status: string, type: string, daily_budget_micros: int}|null */
    public function campaign(GoogleAdsConnection $connection, string $campaignId): ?array
    {
        $this->assertCampaignId($campaignId);

        $campaign = $this->searchCampaigns($connection, "campaign.id = {$campaignId} LIMIT 1")[0] ?? null;

        return $campaign && $campaign['id'] === $campaignId ? $campaign : null;
    }

    /** @return list<array{id: string, resource_name: string, status: string, final_url: string, headlines: list<array{text: string, pinned_field: string}>, descriptions: list<array{text: string, pinned_field: string}>}> */
    public function responsiveSearchAds(GoogleAdsConnection $connection, string $campaignId): array
    {
        $this->assertCampaignId($campaignId);
        $rows = $this->searchRows($connection, "SELECT campaign.id, ad_group_ad.ad.id, ad_group_ad.ad.resource_name, ad_group_ad.ad.final_urls, ad_group_ad.ad.responsive_search_ad.headlines, ad_group_ad.ad.responsive_search_ad.descriptions, ad_group_ad.status FROM ad_group_ad WHERE campaign.id = {$campaignId} AND ad_group_ad.ad.type = RESPONSIVE_SEARCH_AD AND ad_group_ad.status != 'REMOVED' LIMIT 50");

        return collect($rows)->filter(fn (array $row): bool => (string) data_get($row, 'campaign.id') === $campaignId)
            ->map(fn (array $row): array => [
                'id' => (string) data_get($row, 'adGroupAd.ad.id', ''),
                'resource_name' => (string) data_get($row, 'adGroupAd.ad.resourceName', ''),
                'status' => (string) data_get($row, 'adGroupAd.status', ''),
                'final_url' => (string) data_get($row, 'adGroupAd.ad.finalUrls.0', ''),
                'headlines' => $this->adTextAssets(data_get($row, 'adGroupAd.ad.responsiveSearchAd.headlines', [])),
                'descriptions' => $this->adTextAssets(data_get($row, 'adGroupAd.ad.responsiveSearchAd.descriptions', [])),
            ])->filter(fn (array $ad): bool => preg_match('/^[1-9]\d*$/', $ad['id']) === 1)->values()->all();
    }

    /** @return list<array{text: string, match_type: string}> */
    public function campaignKeywords(GoogleAdsConnection $connection, string $campaignId): array
    {
        $this->assertCampaignId($campaignId);
        $rows = $this->searchRows($connection, "SELECT campaign.id, ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type FROM ad_group_criterion WHERE campaign.id = {$campaignId} AND ad_group_criterion.type = KEYWORD AND ad_group_criterion.status != 'REMOVED' LIMIT 100");

        return collect($rows)->filter(fn (array $row): bool => (string) data_get($row, 'campaign.id') === $campaignId)
            ->map(fn (array $row): array => ['text' => (string) data_get($row, 'adGroupCriterion.keyword.text', ''), 'match_type' => (string) data_get($row, 'adGroupCriterion.keyword.matchType', '')])
            ->filter(fn (array $keyword): bool => $keyword['text'] !== '')->values()->all();
    }

    /** @return list<array{city: string, radius: float, units: string}> */
    public function campaignProximities(GoogleAdsConnection $connection, string $campaignId): array
    {
        $this->assertCampaignId($campaignId);
        $rows = $this->searchRows($connection, "SELECT campaign.id, campaign_criterion.proximity.address.city_name, campaign_criterion.proximity.radius, campaign_criterion.proximity.radius_units FROM campaign_criterion WHERE campaign.id = {$campaignId} AND campaign_criterion.type = PROXIMITY AND campaign_criterion.status != 'REMOVED' LIMIT 50");

        return collect($rows)->filter(fn (array $row): bool => (string) data_get($row, 'campaign.id') === $campaignId)
            ->map(fn (array $row): array => ['city' => (string) data_get($row, 'campaignCriterion.proximity.address.cityName', ''), 'radius' => (float) data_get($row, 'campaignCriterion.proximity.radius', 0), 'units' => (string) data_get($row, 'campaignCriterion.proximity.radiusUnits', '')])->values()->all();
    }

    public function updateCampaignName(GoogleAdsConnection $connection, string $campaignId, string $name): void
    {
        $this->assertCampaignId($campaignId);
        $this->mutateCampaign($connection, ['update' => ['resourceName' => $this->campaignResourceName($connection, $campaignId), 'name' => $name], 'updateMask' => 'name']);
    }

    public function updateCampaignBudget(GoogleAdsConnection $connection, string $budgetResourceName, int $amountMicros): void
    {
        $customerId = (string) $connection->customer_id;
        $this->assertCustomerId($customerId);
        if (preg_match('/^customers\/'.$customerId.'\/campaignBudgets\/[1-9]\d*$/', $budgetResourceName) !== 1) {
            throw new RuntimeException('Invalid campaign budget resource.');
        }
        $this->mutateResource($connection, 'campaignBudgets', ['update' => ['resourceName' => $budgetResourceName, 'amountMicros' => (string) $amountMicros], 'updateMask' => 'amount_micros']);
    }

    /** @param list<array{text: string, pinned_field: string}> $headlines
     * @param  list<array{text: string, pinned_field: string}>  $descriptions
     */
    public function updateResponsiveSearchAd(GoogleAdsConnection $connection, string $adId, array $headlines, array $descriptions): void
    {
        $this->assertCampaignId($adId);
        $customerId = (string) $connection->customer_id;
        $this->assertCustomerId($customerId);
        $assets = fn (array $items): array => array_map(function (array $item): array {
            $asset = ['text' => $item['text']];
            if ($item['pinned_field'] !== '' && ! in_array($item['pinned_field'], ['UNSPECIFIED', 'UNKNOWN'], true)) {
                $asset['pinnedField'] = $item['pinned_field'];
            }

            return $asset;
        }, $items);
        $this->mutateResource($connection, 'ads', ['update' => [
            'resourceName' => "customers/{$customerId}/ads/{$adId}",
            'responsiveSearchAd' => ['headlines' => $assets($headlines), 'descriptions' => $assets($descriptions)],
        ], 'updateMask' => 'responsive_search_ad.headlines,responsive_search_ad.descriptions']);
    }

    public function updateCampaignStatus(GoogleAdsConnection $connection, string $campaignId, string $status): void
    {
        $this->assertCampaignId($campaignId);
        if (! in_array($status, ['ENABLED', 'PAUSED'], true)) {
            throw new RuntimeException('Invalid Google Ads campaign status.');
        }

        $this->mutateCampaign($connection, ['update' => [
            'resourceName' => $this->campaignResourceName($connection, $campaignId),
            'status' => $status,
        ], 'updateMask' => 'status']);
    }

    public function removeCampaign(GoogleAdsConnection $connection, string $campaignId): void
    {
        $this->assertCampaignId($campaignId);
        $this->mutateCampaign($connection, ['remove' => $this->campaignResourceName($connection, $campaignId)]);
    }

    /**
     * @return list<array{id: string, resource_name: string, name: string, status: string, type: string, daily_budget_micros: int}>
     */
    protected function searchCampaigns(GoogleAdsConnection $connection, string $filter): array
    {
        $customerId = (string) $connection->customer_id;
        $this->assertCustomerId($customerId);

        return collect($this->searchRows($connection, 'SELECT campaign.id, campaign.resource_name, campaign.name, campaign.status, campaign.advertising_channel_type, campaign_budget.resource_name, campaign_budget.amount_micros, campaign_budget.explicitly_shared, campaign_budget.reference_count, campaign_budget.period FROM campaign WHERE '.$filter))
            ->map(fn (array $result): array => [
                'id' => (string) data_get($result, 'campaign.id', ''),
                'resource_name' => (string) data_get($result, 'campaign.resourceName', ''),
                'name' => (string) data_get($result, 'campaign.name', ''),
                'status' => (string) data_get($result, 'campaign.status', ''),
                'type' => (string) data_get($result, 'campaign.advertisingChannelType', ''),
                'daily_budget_micros' => (int) data_get($result, 'campaignBudget.amountMicros', 0),
                'budget_resource_name' => (string) data_get($result, 'campaignBudget.resourceName', ''),
                'budget_shared' => data_get($result, 'campaignBudget.explicitlyShared') !== false || (int) data_get($result, 'campaignBudget.referenceCount', 0) > 1,
                'budget_period' => (string) data_get($result, 'campaignBudget.period', ''),
            ])
            ->filter(fn (array $campaign): bool => preg_match('/^\d+$/', $campaign['id']) === 1)
            ->values()->all();
    }

    /** @param array<string, mixed> $operation */
    protected function mutateCampaign(GoogleAdsConnection $connection, array $operation): void
    {
        $this->mutateResource($connection, 'campaigns', $operation);
    }

    /** @return list<array<string, mixed>> */
    protected function searchRows(GoogleAdsConnection $connection, string $query): array
    {
        $customerId = (string) $connection->customer_id;
        $this->assertCustomerId($customerId);
        $response = $this->request($connection, $connection->login_customer_id)
            ->post($this->url("customers/{$customerId}/googleAds:searchStream"), ['query' => $query])->throw()->json();
        if (! is_array($response)) {
            throw new RuntimeException('Google Ads returned an invalid search response.');
        }

        return collect($response)->flatMap(fn (mixed $batch): array => is_array($batch) ? ($batch['results'] ?? []) : [])->filter(fn (mixed $row): bool => is_array($row))->values()->all();
    }

    /** @return list<array{text: string, pinned_field: string}> */
    protected function adTextAssets(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)->filter(fn (mixed $item): bool => is_array($item))->map(fn (array $item): array => ['text' => (string) ($item['text'] ?? ''), 'pinned_field' => (string) ($item['pinnedField'] ?? '')])->values()->all();
    }

    /** @param array<string, mixed> $operation */
    protected function mutateResource(GoogleAdsConnection $connection, string $resource, array $operation): void
    {
        $customerId = (string) $connection->customer_id;
        $this->assertCustomerId($customerId);
        $response = $this->request($connection, $connection->login_customer_id)
            ->post($this->url("customers/{$customerId}/{$resource}:mutate"), [
                'operations' => [$operation],
                'partialFailure' => false,
            ])->throw()->json();

        if (! is_array($response) || ! is_string(data_get($response, 'results.0.resourceName'))) {
            throw new RuntimeException('Google Ads did not confirm the campaign change.');
        }
    }

    protected function campaignResourceName(GoogleAdsConnection $connection, string $campaignId): string
    {
        $customerId = (string) $connection->customer_id;
        $this->assertCustomerId($customerId);

        return "customers/{$customerId}/campaigns/{$campaignId}";
    }

    protected function assertCampaignId(string $campaignId): void
    {
        if (preg_match('/^[1-9]\d*$/', $campaignId) !== 1) {
            throw new RuntimeException('Invalid Google Ads campaign ID.');
        }
    }

    protected function request(GoogleAdsConnection $connection, ?string $loginCustomerId = null): PendingRequest
    {
        $request = Http::acceptJson()->asJson()->withToken($this->oauth->accessToken($connection))
            ->connectTimeout(5)->timeout(20);
        if ($loginCustomerId !== null) {
            $request->withHeaders(['login-customer-id' => $loginCustomerId]);
        }

        return $request;
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('services.google_ads.api_url'), '/').'/'.$path;
    }

    protected function assertCustomerId(string $customerId): void
    {
        if (preg_match('/^\d{10}$/', $customerId) !== 1) {
            throw new RuntimeException('Google Ads customer IDs must contain 10 digits.');
        }
    }
}
