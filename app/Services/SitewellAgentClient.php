<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SitewellAgentClient
{
    /** @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('get', $path, $query);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function post(string $path, array $data): array
    {
        return $this->request('post', $path, $data);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $data): array
    {
        $endpoint = config('services.sitewell_agent.endpoint');
        $token = config('services.sitewell_agent.token');
        if (! is_string($endpoint) || ! filter_var($endpoint, FILTER_VALIDATE_URL) || parse_url($endpoint, PHP_URL_SCHEME) !== 'https'
            || parse_url($endpoint, PHP_URL_USER) !== null || parse_url($endpoint, PHP_URL_QUERY) !== null || parse_url($endpoint, PHP_URL_FRAGMENT) !== null
            || ! str_ends_with($endpoint, '/agent/v1/work') || ! is_string($token) || strlen($token) < 32) {
            throw new RuntimeException('Configure SITEWELL_AGENT_ENDPOINT (HTTPS /api/agent/v1/work) and SITEWELL_AGENT_TOKEN before using the agent API.');
        }
        $allowed = $method === 'get'
            ? '~^(?:work|websites(?:/[1-9][0-9]*(?:/evidence/[a-z-]+|/tasks/[1-9][0-9]*)?)?)$~D'
            : '~^websites/[1-9][0-9]*/tasks(?:/[1-9][0-9]*/(?:draft-pr|blocked))?$~D';
        if (! preg_match($allowed, $path)) {
            throw new RuntimeException('Unsupported agent API path.');
        }
        $url = substr($endpoint, 0, -strlen('work')).$path;
        try {
            $response = Http::acceptJson()->withToken($token)->connectTimeout(5)->timeout(20)->withoutRedirecting()->{$method}($url, $data);
        } catch (Throwable) {
            throw new RuntimeException('Could not reach the Sitewell agent API. Check the connection without exposing credentials.');
        }
        if (! $response->successful() || $response->json('read_only') !== ($method === 'get')) {
            $detail = in_array($response->status(), [409, 422], true) && is_string($response->json('message'))
                ? ' '.mb_strcut($response->json('message'), 0, 1000, 'UTF-8') : '';
            throw new RuntimeException('Sitewell agent API did not return a valid response (HTTP '.$response->status().').'.$detail);
        }

        return $response->json();
    }
}
