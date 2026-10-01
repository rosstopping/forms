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

    /** @return list<array{name: string, category: string, primary: bool}> */
    public function conversionActions(GoogleAdsConnection $connection): array
    {
        $customerId = (string) $connection->customer_id;
        $this->assertCustomerId($customerId);
        $response = $this->request($connection, $connection->login_customer_id)
            ->post($this->url("customers/{$customerId}/googleAds:searchStream"), [
                'query' => "SELECT conversion_action.name, conversion_action.category, conversion_action.primary_for_goal FROM conversion_action WHERE conversion_action.status = 'ENABLED' LIMIT 20",
            ])->throw()->json();

        return collect($response)
            ->flatMap(fn (mixed $batch): array => is_array($batch) ? ($batch['results'] ?? []) : [])
            ->map(fn (array $result): array => [
                'name' => (string) data_get($result, 'conversionAction.name', ''),
                'category' => (string) data_get($result, 'conversionAction.category', ''),
                'primary' => (bool) data_get($result, 'conversionAction.primaryForGoal', false),
            ])->filter(fn (array $action): bool => $action['name'] !== '')
            ->values()->all();
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
