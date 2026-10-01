<?php

namespace App\Services;

use App\Models\GoogleAdsConnection;
use Illuminate\Http\Client\PendingRequest;
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

    /** @return array{id: string, name: string, currency: string} */
    public function customer(GoogleAdsConnection $connection, string $customerId, ?string $loginCustomerId = null): array
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
        if (! is_array($customer) || ($customer['manager'] ?? false)) {
            throw new RuntimeException('Choose a Google Ads client account, not a manager account.');
        }

        return [
            'id' => $customerId,
            'name' => (string) ($customer['descriptiveName'] ?? $customerId),
            'currency' => (string) ($customer['currencyCode'] ?? ''),
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
