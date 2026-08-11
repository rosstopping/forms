<?php

namespace App\Services;

use App\Models\WebsiteRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GithubAppClient
{
    /** @return array<string, mixed> */
    public function installation(int $installationId): array
    {
        return $this->appRequest()->get("app/installations/{$installationId}")->throw()->json();
    }

    /** @return array<int, array<string, mixed>> */
    public function repositories(int $installationId): array
    {
        $token = $this->installationToken($installationId);

        return $this->request($token)
            ->get('installation/repositories', ['per_page' => 100])
            ->throw()
            ->collect('repositories')
            ->all();
    }

    /** @return array{pull_request: array<string, mixed>, files: array<int, array<string, mixed>>} */
    public function pullRequestDetails(WebsiteRepository $repository, int $pullRequestNumber): array
    {
        $token = $this->installationToken($repository->installation->installation_id);
        $request = $this->request($token);

        return [
            'pull_request' => $request->get("repos/{$repository->full_name}/pulls/{$pullRequestNumber}")->throw()->json(),
            'files' => $request->get("repos/{$repository->full_name}/pulls/{$pullRequestNumber}/files", ['per_page' => 100])->throw()->json(),
        ];
    }

    public function installationToken(int $installationId): string
    {
        $token = $this->appRequest()
            ->post("app/installations/{$installationId}/access_tokens")
            ->throw()
            ->json('token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('GitHub did not return an installation access token.');
        }

        return $token;
    }

    protected function appRequest(): PendingRequest
    {
        return $this->request($this->jwt());
    }

    protected function request(string $token): PendingRequest
    {
        return Http::baseUrl((string) config('services.github.api_url'))
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => config('app.name').' GitHub App',
            ])
            ->withToken($token)
            ->connectTimeout(5)
            ->timeout(15)
            ->retry([200, 500], throw: false);
    }

    protected function jwt(): string
    {
        $appId = (string) config('services.github.app_id');
        $privateKey = Str::of((string) config('services.github.private_key'))->replace('\\n', "\n")->toString();

        if ($appId === '' || $privateKey === '') {
            throw new RuntimeException('The GitHub App is not configured.');
        }

        $now = now()->timestamp;
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode(['iat' => $now - 60, 'exp' => $now + 540, 'iss' => $appId], JSON_THROW_ON_ERROR));
        $unsignedToken = $header.'.'.$payload;

        if (! openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The GitHub App private key is invalid.');
        }

        return $unsignedToken.'.'.$this->base64UrlEncode($signature);
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
