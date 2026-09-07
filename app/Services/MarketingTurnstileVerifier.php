<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MarketingTurnstileVerifier
{
    public function enabled(): bool
    {
        return (bool) config('services.turnstile.marketing.enabled')
            && filled(config('services.turnstile.marketing.site_key'))
            && filled(config('services.turnstile.marketing.secret_key'));
    }

    public function passes(?string $token, ?string $ipAddress, string $requestHost): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(config('services.turnstile.connect_timeout', 3))
                ->timeout(config('services.turnstile.timeout', 5))
                ->post(config('services.turnstile.verify_url'), [
                    'secret' => config('services.turnstile.marketing.secret_key'),
                    'response' => $token,
                    'remoteip' => $ipAddress,
                ]);
        } catch (ConnectionException $exception) {
            report($exception);

            return false;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            return false;
        }

        $expectedHost = Str::lower((string) (config('services.turnstile.marketing.hostname') ?: $requestHost));

        return Str::lower((string) $response->json('hostname')) === $expectedHost;
    }
}
