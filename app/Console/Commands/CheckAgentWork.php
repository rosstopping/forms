<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('agent:check')]
#[Description('Read the configured Sitewell agent work API without changing any work')]
class CheckAgentWork extends Command
{
    public function handle(): int
    {
        $endpoint = config('services.sitewell_agent.endpoint');
        $token = config('services.sitewell_agent.token');
        if (! is_string($endpoint) || ! filter_var($endpoint, FILTER_VALIDATE_URL) || parse_url($endpoint, PHP_URL_SCHEME) !== 'https'
            || parse_url($endpoint, PHP_URL_USER) !== null || parse_url($endpoint, PHP_URL_QUERY) !== null
            || ! is_string($token) || strlen($token) < 32) {
            $this->error('Configure SITEWELL_AGENT_ENDPOINT (HTTPS) and SITEWELL_AGENT_TOKEN before enabling the morning check.');

            return self::FAILURE;
        }
        try {
            $response = Http::acceptJson()->withToken($token)->connectTimeout(5)->timeout(20)->withoutRedirecting()->get($endpoint);
        } catch (Throwable) {
            $this->error('Could not reach the Sitewell agent API. Check the connection without exposing credentials.');

            return self::FAILURE;
        }
        if (! $response->successful() || $response->json('read_only') !== true || ! is_array($response->json('summary'))) {
            $this->error('Sitewell agent API did not return a valid work summary (HTTP '.$response->status().').');

            return self::FAILURE;
        }
        $this->line(json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
