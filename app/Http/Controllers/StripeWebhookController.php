<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StripeBillingClient;
use App\Support\MembershipPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeBillingClient $stripe): JsonResponse
    {
        $payload = $request->getContent();
        $this->verifySignature($payload, (string) $request->header('Stripe-Signature'));
        $event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $type = $event['type'] ?? null;
        $object = data_get($event, 'data.object', []);

        if ($type === 'checkout.session.completed') {
            $user = User::query()->find($object['client_reference_id'] ?? data_get($object, 'metadata.user_id'));

            if ($user) {
                $user->update(['stripe_customer_id' => $object['customer'] ?? null]);

                if (is_string($object['subscription'] ?? null)) {
                    $this->syncSubscription($stripe->subscription($object['subscription']));
                }
            }
        }

        if (in_array($type, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            $this->syncSubscription($object);
        }

        return response()->json(['received' => true]);
    }

    /** @param array<string, mixed> $subscription */
    private function syncSubscription(array $subscription): void
    {
        $customerId = $subscription['customer'] ?? null;
        $userId = data_get($subscription, 'metadata.user_id');
        $user = User::query()
            ->when($customerId, fn ($query) => $query->where('stripe_customer_id', $customerId))
            ->when(! $customerId && $userId, fn ($query) => $query->whereKey($userId))
            ->first();

        if (! $user) {
            return;
        }

        $priceId = data_get($subscription, 'items.data.0.price.id');
        $user->update([
            'stripe_customer_id' => $customerId ?: $user->stripe_customer_id,
            'stripe_subscription_id' => $subscription['id'] ?? null,
            'membership_tier' => MembershipPlan::tierForPrice($priceId) ?: $user->membership_tier,
            'membership_status' => $subscription['status'] ?? null,
            'membership_current_period_end' => isset($subscription['current_period_end']) ? now()->setTimestamp($subscription['current_period_end']) : null,
            'membership_cancel_at' => isset($subscription['cancel_at']) ? now()->setTimestamp($subscription['cancel_at']) : null,
        ]);
    }

    private function verifySignature(string $payload, string $signatureHeader): void
    {
        $secret = (string) config('services.stripe.webhook_secret');
        preg_match('/(?:^|,)t=([0-9]+)/', $signatureHeader, $timestampMatch);
        preg_match_all('/(?:^|,)v1=([a-f0-9]+)/', $signatureHeader, $signatureMatches);
        $timestamp = isset($timestampMatch[1]) ? (int) $timestampMatch[1] : 0;
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $valid = $secret !== '' && abs(time() - $timestamp) <= 300
            && collect($signatureMatches[1] ?? [])->contains(fn (string $signature): bool => hash_equals($expected, $signature));

        if (! $valid) {
            throw new BadRequestHttpException('Invalid Stripe webhook signature.');
        }
    }
}
