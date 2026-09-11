<?php

namespace App\Services;

use App\Models\FormSubmissionEmailDelivery;
use App\Models\WebsiteMailConnection;

class ManagedAutoresponderLimitPolicy
{
    public function suppressionReason(WebsiteMailConnection $connection, string $recipient): ?string
    {
        if ($connection->mode !== WebsiteMailConnection::MODE_MANAGED) {
            return null;
        }

        $deliveries = FormSubmissionEmailDelivery::query()
            ->where('website_id', $connection->website_id)
            ->where('status', 'sent');

        $connectedAt = $connection->connected_at ?? $connection->created_at ?? now();
        $stageDays = max(1, (int) config('forms.autoresponder_limits.warmup_stage_days', 7));
        $stage = min(2, intdiv(max(0, (int) $connectedAt->diffInDays(now())), $stageDays));
        $warmupLimits = config('forms.autoresponder_limits.warmup_daily', [25, 50, 100]);
        $dailyLimit = $connection->daily_limit_override ?? (int) ($warmupLimits[$stage] ?? 100);
        $monthlyLimit = $connection->monthly_limit_override ?? (int) config('forms.autoresponder_limits.monthly', 1000);

        if ((clone $deliveries)->where('sent_at', '>=', now()->startOfDay())->count() >= $dailyLimit) {
            return 'managed_daily_limit';
        }

        if ((clone $deliveries)->where('sent_at', '>=', now()->subDays(30))->count() >= $monthlyLimit) {
            return 'managed_monthly_limit';
        }

        $recipientDeliveries = (clone $deliveries)->where('recipient', $recipient);

        if ((clone $recipientDeliveries)->where('sent_at', '>=', now()->subHour())->count() >= (int) config('forms.autoresponder_limits.recipient_hourly', 2)) {
            return 'recipient_hourly_limit';
        }

        if ((clone $recipientDeliveries)->where('sent_at', '>=', now()->subDay())->count() >= (int) config('forms.autoresponder_limits.recipient_daily', 5)) {
            return 'recipient_daily_limit';
        }

        return null;
    }
}
