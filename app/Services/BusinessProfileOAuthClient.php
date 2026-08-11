<?php

namespace App\Services;

use App\Models\BusinessProfileConnection;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BusinessProfileOAuthClient
{
    public function authorizationUrl(string $state): string
    {
        $this->ensureConfigured();

        return (string) config('services.google.oauth_url').'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('admin.business-profile.callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/business.manage',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    public function authorize(Website $website, User $user, string $code): BusinessProfileConnection
    {
        $tokens = $this->tokenRequest(['client_id' => config('services.google.client_id'), 'client_secret' => config('services.google.client_secret'), 'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => route('admin.business-profile.callback')]);

        return BusinessProfileConnection::query()->updateOrCreate(['website_id' => $website->id], [
            'connected_by' => $user->id,
            'access_token' => $tokens['access_token'],
            'access_token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
            'refresh_token' => $tokens['refresh_token'] ?? $website->businessProfileConnection?->refresh_token,
        ]);
    }

    public function accessToken(BusinessProfileConnection $connection): string
    {
        if ($connection->access_token_expires_at?->isAfter(now()->addMinutes(5))) {
            return $connection->access_token;
        }

        return Cache::lock('business-profile-oauth-refresh-'.$connection->id, 20)->block(5, function () use ($connection): string {
            $connection->refresh();
            if ($connection->access_token_expires_at?->isAfter(now()->addMinutes(5))) {
                return $connection->access_token;
            }
            if (! $connection->refresh_token) {
                throw new RuntimeException('Google Business Profile authorization expired. Reconnect it to continue.');
            }
            $tokens = $this->tokenRequest(['client_id' => config('services.google.client_id'), 'client_secret' => config('services.google.client_secret'), 'grant_type' => 'refresh_token', 'refresh_token' => $connection->refresh_token]);
            $connection->update(['access_token' => $tokens['access_token'], 'access_token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)), 'refresh_token' => $tokens['refresh_token'] ?? $connection->refresh_token]);

            return $connection->fresh()->access_token;
        });
    }

    /** @param array<string, mixed> $parameters @return array<string, mixed> */
    protected function tokenRequest(array $parameters): array
    {
        $this->ensureConfigured();
        $tokens = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(15)->post((string) config('services.google.token_url'), $parameters)->throw()->json();
        if (! is_array($tokens) || ! is_string($tokens['access_token'] ?? null)) {
            throw new RuntimeException('Google did not return an access token.');
        }

        return $tokens;
    }

    protected function ensureConfigured(): void
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            throw new RuntimeException('Google OAuth is not configured.');
        }
    }
}
