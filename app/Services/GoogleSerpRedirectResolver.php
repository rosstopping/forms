<?php

namespace App\Services;

use App\Data\SerpResult;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class GoogleSerpRedirectResolver
{
    public static function isRedirect(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower($parts['host'] ?? ''), ['google.com', 'www.google.com'], true)
            && in_array($parts['path'] ?? '', ['/goto', '/url'], true)
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && (! isset($parts['port']) || $parts['port'] === 443);
    }

    /** @param Collection<int, SerpResult> $results
     * @return Collection<int, SerpResult>
     */
    public function resolve(Collection $results): Collection
    {
        $redirects = $results->filter(fn (SerpResult $result): bool => self::isRedirect($result->url));
        if ($redirects->isEmpty()) {
            return $results;
        }

        $destinations = [];
        $requests = [];
        foreach ($redirects as $key => $result) {
            parse_str((string) parse_url($result->url, PHP_URL_QUERY), $query);
            $direct = $query['q'] ?? $query['url'] ?? null;
            if (is_string($direct) && $this->validDestination($direct)) {
                $destinations[$key] = $direct;
            } else {
                // Request only Google's redirect endpoint, never the destination website.
                $requests[$key] = 'https://www.google.com'.parse_url($result->url, PHP_URL_PATH).'?'.parse_url($result->url, PHP_URL_QUERY);
            }
        }
        if ($requests !== []) {
            $responses = Http::pool(function (Pool $pool) use ($requests): array {
                $pending = [];
                foreach ($requests as $key => $url) {
                    $pending[] = $pool->as((string) $key)->withoutRedirecting()->connectTimeout(3)->timeout(8)->get($url);
                }

                return $pending;
            }, concurrency: 10);
            foreach ($responses as $key => $response) {
                if ($response instanceof Response && in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                    $destination = $response->header('Location');
                    if ($this->validDestination($destination)) {
                        $destinations[$key] = $destination;
                    }
                }
            }
        }

        return $results->map(fn (SerpResult $result, int $key): SerpResult => isset($destinations[$key])
            ? new SerpResult($result->position, $destinations[$key], strtolower((string) parse_url($destinations[$key], PHP_URL_HOST)), $result->title, $result->description, $result->websiteName)
            : $result);
    }

    private function validDestination(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || ! filter_var($url, FILTER_VALIDATE_URL) || self::isRedirect($url)) {
            return false;
        }
        $host = strtolower($parts['host'] ?? '');

        return str_contains($host, '.') && ! in_array($host, ['google.com', 'www.google.com'], true)
            && ! filter_var($host, FILTER_VALIDATE_IP)
            && ! str_ends_with($host, '.local') && ! str_ends_with($host, '.localhost');
    }
}
