<?php

namespace App\Http\Controllers;

use App\Models\MarketingConversion;
use App\Models\User;
use App\Support\MarketingJourney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CalWebhookController extends Controller
{
    public function __invoke(Request $request, MarketingJourney $journey): JsonResponse
    {
        $rawPayload = $request->getContent();
        $this->verifySignature($rawPayload, (string) $request->header('X-Cal-Signature-256'));

        try {
            $event = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestHttpException('Invalid Cal.com webhook payload.');
        }

        $trigger = data_get($event, 'triggerEvent');
        $payload = data_get($event, 'payload', $event);
        $bookingToken = data_get($payload, 'metadata.sitewell_booking');
        $bookingUid = data_get($payload, 'uid');

        if ($trigger === 'BOOKING_CREATED' && is_string($bookingToken) && is_string($bookingUid) && $bookingUid !== '') {
            $click = MarketingConversion::query()->where('event_id', $bookingToken)->where('name', 'book_call_clicked')->first();

            if ($click) {
                $journey->record('call_booked', $bookingUid, $click->attribution ?? []);
            }
        }
        $attendeeEmails = collect(data_get($payload, 'attendees', []))
            ->pluck('email')
            ->filter(fn (mixed $email): bool => is_string($email))
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->unique()
            ->values();

        if (! is_string($trigger) || $attendeeEmails->isEmpty()) {
            return response()->json(['received' => true]);
        }

        $user = User::query()
            ->whereIn('email', $attendeeEmails)
            ->whereHas('websiteAudits', fn ($query) => $query->whereNotNull('claimed_at'))
            ->first();

        if (! $user) {
            return response()->json(['received' => true]);
        }

        if ($trigger === 'BOOKING_CREATED' && is_string($bookingUid) && $bookingUid !== '') {
            $audit = $user->websiteAudits()->whereNotNull('claimed_at')->latest('claimed_at')->first();
            $journey->record('call_booked', $bookingUid, $audit?->marketing_attribution ?? []);
        }

        if (in_array($trigger, ['BOOKING_CREATED', 'BOOKING_RESCHEDULED'], true)) {
            $user->forceFill([
                'onboarding_call_booking_started_at' => $user->onboarding_call_booking_started_at ?? now(),
                'onboarding_call_booked_at' => now(),
                'onboarding_call_completed_at' => null,
            ])->save();
        }

        if (in_array($trigger, ['BOOKING_CANCELLED', 'BOOKING_REJECTED'], true)) {
            $user->forceFill([
                'onboarding_call_booked_at' => null,
                'onboarding_call_completed_at' => null,
            ])->save();
        }

        if ($trigger === 'MEETING_ENDED') {
            $user->forceFill([
                'onboarding_call_booking_started_at' => $user->onboarding_call_booking_started_at ?? now(),
                'onboarding_call_booked_at' => $user->onboarding_call_booked_at ?? now(),
                'onboarding_call_completed_at' => now(),
            ])->save();
        }

        return response()->json(['received' => true]);
    }

    private function verifySignature(string $payload, string $signature): void
    {
        $secret = (string) config('services.cal.webhook_secret');
        $expected = hash_hmac('sha256', $payload, $secret);

        if ($secret === '' || $signature === '' || ! hash_equals($expected, $signature)) {
            throw new BadRequestHttpException('Invalid Cal.com webhook signature.');
        }
    }
}
