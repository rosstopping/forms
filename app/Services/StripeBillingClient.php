<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeBillingClient
{
    /** @return array{id: string, url: string} */
    public function createCheckoutSession(User $user, string $priceId, string $successUrl, string $cancelUrl): array
    {
        $payload = [
            'mode' => 'subscription',
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $user->id,
            'metadata[user_id]' => (string) $user->id,
            'subscription_data[metadata][user_id]' => (string) $user->id,
            'allow_promotion_codes' => 'true',
        ];

        if ($user->stripe_customer_id) {
            $payload['customer'] = $user->stripe_customer_id;
        } else {
            $payload['customer_email'] = $user->email;
        }

        return $this->post('checkout/sessions', $payload);
    }

    /** @return array{id: string, url: string} */
    public function createPortalSession(User $user, string $returnUrl): array
    {
        if (! $user->stripe_customer_id) {
            throw new RuntimeException('This account does not have a Stripe customer yet.');
        }

        return $this->post('billing_portal/sessions', [
            'customer' => $user->stripe_customer_id,
            'return_url' => $returnUrl,
        ]);
    }

    /** @return array<string, mixed> */
    public function subscription(string $subscriptionId): array
    {
        return $this->client()->get('subscriptions/'.$subscriptionId)->throw()->json();
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $response = $this->client()->asForm()->post($path, $payload)->throw();
        $data = $response->json();

        if (! is_array($data) || ! is_string($data['id'] ?? null) || ! is_string($data['url'] ?? null)) {
            throw new RuntimeException('Stripe did not return a valid hosted billing session.');
        }

        return $data;
    }

    private function client(): PendingRequest
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            throw new RuntimeException('Stripe billing is not configured.');
        }

        return Http::baseUrl((string) config('services.stripe.api_url'))
            ->withToken($secret)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->retry([250, 750], throw: false);
    }
}
