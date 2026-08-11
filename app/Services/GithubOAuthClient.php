<?php

namespace App\Services;

use App\Models\GithubUserAuthorization;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GithubOAuthClient
{
    public function authorizationUrl(string $state): string
    {
        $this->ensureConfigured();

        return (string) config('services.github.oauth_url').'/authorize?'.http_build_query([
            'client_id' => config('services.github.client_id'),
            'redirect_uri' => route('admin.github.callback'),
            'state' => $state,
        ]);
    }

    public function authorize(User $user, string $code): GithubUserAuthorization
    {
        $tokens = $this->tokenRequest([
            'client_id' => config('services.github.client_id'),
            'client_secret' => config('services.github.client_secret'),
            'code' => $code,
            'redirect_uri' => route('admin.github.callback'),
        ]);
        $githubUser = $this->apiRequest($tokens['access_token'])->get('user')->throw()->json();

        return GithubUserAuthorization::query()->updateOrCreate(
            ['user_id' => $user->id],
            $this->authorizationAttributes($tokens, $githubUser),
        );
    }

    public function accessToken(GithubUserAuthorization $authorization): string
    {
        if (! $authorization->access_token_expires_at || $authorization->access_token_expires_at->isAfter(now()->addMinutes(5))) {
            return $authorization->access_token;
        }

        return Cache::lock('github-oauth-refresh-'.$authorization->id, 20)->block(5, function () use ($authorization): string {
            $authorization->refresh();

            if ($authorization->access_token_expires_at?->isAfter(now()->addMinutes(5))) {
                return $authorization->access_token;
            }

            if (! $authorization->refresh_token || $authorization->refresh_token_expires_at?->isPast()) {
                throw new RuntimeException('The GitHub authorization has expired. Reconnect GitHub to continue.');
            }

            $tokens = $this->tokenRequest([
                'client_id' => config('services.github.client_id'),
                'client_secret' => config('services.github.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $authorization->refresh_token,
            ]);

            $authorization->update($this->tokenAttributes($tokens));

            return $authorization->fresh()->access_token;
        });
    }

    public function canAccessInstallation(GithubUserAuthorization $authorization, int $installationId): bool
    {
        $page = 1;

        do {
            $installations = $this->apiRequest($this->accessToken($authorization))
                ->get('user/installations', ['per_page' => 100, 'page' => $page])
                ->throw()
                ->json('installations', []);

            if (collect($installations)->contains('id', $installationId)) {
                return true;
            }

            $page++;
        } while (count($installations) === 100);

        return false;
    }

    /** @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    protected function tokenRequest(array $parameters): array
    {
        $this->ensureConfigured();

        $tokens = Http::acceptJson()
            ->asForm()
            ->connectTimeout(5)
            ->timeout(15)
            ->post((string) config('services.github.oauth_url').'/access_token', $parameters)
            ->throw()
            ->json();

        if (! is_array($tokens) || ! is_string($tokens['access_token'] ?? null)) {
            throw new RuntimeException('GitHub did not return a user access token.');
        }

        return $tokens;
    }

    protected function apiRequest(string $token): PendingRequest
    {
        return Http::baseUrl((string) config('services.github.api_url'))
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2026-03-10',
                'User-Agent' => config('app.name').' GitHub App',
            ])
            ->withToken($token)
            ->connectTimeout(5)
            ->timeout(15);
    }

    /** @param array<string, mixed> $tokens
     * @param  array<string, mixed>  $githubUser
     * @return array<string, mixed>
     */
    protected function authorizationAttributes(array $tokens, array $githubUser): array
    {
        return [
            'github_user_id' => $githubUser['id'],
            'github_login' => $githubUser['login'],
            ...$this->tokenAttributes($tokens),
        ];
    }

    /** @param array<string, mixed> $tokens
     * @return array<string, mixed>
     */
    protected function tokenAttributes(array $tokens): array
    {
        return [
            'access_token' => $tokens['access_token'],
            'access_token_expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'refresh_token_expires_at' => isset($tokens['refresh_token_expires_in']) ? now()->addSeconds((int) $tokens['refresh_token_expires_in']) : null,
        ];
    }

    protected function ensureConfigured(): void
    {
        if (! config('services.github.client_id') || ! config('services.github.client_secret')) {
            throw new RuntimeException('GitHub OAuth is not configured.');
        }
    }
}
