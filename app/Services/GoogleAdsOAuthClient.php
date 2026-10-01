<?php

namespace App\Services;

use App\Models\GoogleAdsConnection;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleAdsOAuthClient
{
    public function authorizationUrl(string $state): string
    {
        $this->ensureConfigured();

        return (string) config('services.google.oauth_url').'?'.http_build_query([
            'client_id' => config('services.google_ads.client_id'),
            'redirect_uri' => route('admin.google-ads.callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/adwords',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function authorize(Website $website, User $user, string $code): GoogleAdsConnection
    {
        $tokens = $this->tokenRequest([
            'client_id' => config('services.google_ads.client_id'),
            'client_secret' => config('services.google_ads.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('admin.google-ads.callback'),
        ]);
        $existing = $website->googleAdsConnection;

        return GoogleAdsConnection::query()->updateOrCreate(['website_id' => $website->id], [
            'connected_by' => $user->id,
            'customer_id' => null,
            'login_customer_id' => null,
            'customer_name' => null,
            'currency_code' => null,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $existing?->refresh_token,
            'access_token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
        ]);
    }

    public function accessToken(GoogleAdsConnection $connection): string
    {
        if ($connection->access_token_expires_at?->isAfter(now()->addMinutes(5))) {
            return $connection->access_token;
        }

        return Cache::lock('google-ads-oauth-refresh-'.$connection->id, 20)->block(5, function () use ($connection): string {
            $connection->refresh();
            if ($connection->access_token_expires_at?->isAfter(now()->addMinutes(5))) {
                return $connection->access_token;
            }
            if (! $connection->refresh_token) {
                throw new RuntimeException('Google Ads authorization expired. Reconnect Google to continue.');
            }

            $tokens = $this->tokenRequest([
                'client_id' => config('services.google_ads.client_id'),
                'client_secret' => config('services.google_ads.client_secret'),
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ]);
            $connection->update([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $connection->refresh_token,
                'access_token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
            ]);

            return $connection->fresh()->access_token;
        });
    }

    /** @param array<string, string> $parameters
     * @return array<string, mixed>
     */
    protected function tokenRequest(array $parameters): array
    {
        $this->ensureConfigured();
        $tokens = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(15)
            ->post((string) config('services.google.token_url'), $parameters)->throw()->json();
        if (! is_array($tokens) || ! is_string($tokens['access_token'] ?? null)) {
            throw new RuntimeException('Google did not return an access token.');
        }

        return $tokens;
    }

    protected function ensureConfigured(): void
    {
        if (! config('services.google_ads.client_id') || ! config('services.google_ads.client_secret')) {
            throw new RuntimeException('Google Ads OAuth is not configured.');
        }
    }
}
