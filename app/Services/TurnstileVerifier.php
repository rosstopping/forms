<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TurnstileVerifier
{
    public function passes(Website $website, ?string $token, ?string $ipAddress): bool
    {
        if (! $website->turnstile_enabled || $website->auto_discovered) {
            return true;
        }

        if (blank($website->turnstile_secret_key) || blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(config('services.turnstile.connect_timeout', 3))
                ->timeout(config('services.turnstile.timeout', 5))
                ->post(config('services.turnstile.verify_url'), [
                    'secret' => $website->turnstile_secret_key,
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

        $hostname = Str::lower((string) $response->json('hostname'));

        return $hostname !== '' && $website->domains()->where('domain', $hostname)->exists();
    }
}
