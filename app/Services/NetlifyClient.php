<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class NetlifyClient
{
    /** @param array<string, mixed> $repository
     * @return array{id: string, url: string, domain: string}
     */
    public function deployRepository(array $repository): array
    {
        $token = (string) config('services.netlify.token');

        if ($token === '') {
            throw new RuntimeException('Netlify is not configured. Add a NETLIFY_ACCESS_TOKEN first.');
        }

        $response = Http::baseUrl((string) config('services.netlify.api_url'))
            ->withToken($token)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(45)
            ->retry([250, 750], throw: false)
            ->post('sites', [
                'repo' => [
                    'provider' => 'github',
                    'repo' => $repository['full_name'],
                    'repo_id' => $repository['id'],
                    'private' => $repository['private'] ?? false,
                    'branch' => $repository['default_branch'] ?? 'main',
                    'cmd' => 'npm run build',
                    'dir' => '_site',
                ],
            ])
            ->throw();

        $url = $response->json('ssl_url') ?: $response->json('url');
        $id = $response->json('id');
        $domain = $response->json('default_domain') ?: parse_url((string) $url, PHP_URL_HOST);

        if (! is_string($id) || ! is_string($url) || ! is_string($domain)) {
            throw new RuntimeException('Netlify did not return the new website details.');
        }

        return ['id' => $id, 'url' => $url, 'domain' => $domain];
    }
}
