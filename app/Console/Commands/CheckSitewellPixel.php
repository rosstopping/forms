<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

#[Signature('sitewell:pixel:check
    {site-key : Public sw_ site key}
    {url : Customer page URL to request}
    {--asset-url= : Override the configured Pixel JavaScript URL}
    {--api-url= : Override the configured Pixel API base URL}')]
#[Description('Check the deployed Sitewell Pixel asset and public payload endpoint')]
class CheckSitewellPixel extends Command
{
    public function handle(): int
    {
        $siteKey = (string) $this->argument('site-key');
        $pageUrl = (string) $this->argument('url');

        if (! preg_match('/^sw_[a-z0-9]{20,}$/i', $siteKey)) {
            $this->error('The site key must be a valid public sw_ key.');

            return self::INVALID;
        }

        if (! filter_var($pageUrl, FILTER_VALIDATE_URL) || ! Str::startsWith($pageUrl, ['http://', 'https://'])) {
            $this->error('The page URL must be an absolute HTTP or HTTPS URL.');

            return self::INVALID;
        }

        $assetUrl = (string) ($this->option('asset-url') ?: config('services.sitewell.pixel_asset_url'));
        $apiUrl = (string) ($this->option('api-url') ?: config('services.sitewell.pixel_api_url'));

        try {
            $assetResponse = Http::connectTimeout(3)->timeout(8)->get($assetUrl);
            $payloadResponse = Http::connectTimeout(3)->timeout(8)->acceptJson()->get(
                rtrim($apiUrl, '/').'/'.rawurlencode($siteKey),
                ['url' => $pageUrl],
            );
        } catch (ConnectionException $exception) {
            $this->error('Pixel check could not connect: '.$exception->getMessage());

            return self::FAILURE;
        }

        $checks = [
            ['Pixel asset reachable', $assetResponse->successful()],
            ['Pixel asset recognizable', $this->isPixelAsset($assetResponse)],
            ['Payload endpoint reachable', $payloadResponse->successful()],
            ['Payload shape valid', $this->hasValidPayload($payloadResponse)],
            ['Public CORS enabled', $payloadResponse->header('Access-Control-Allow-Origin') === '*'],
            ['Payload cacheable', $this->isCacheable($payloadResponse)],
        ];

        $this->table(
            ['Check', 'Result'],
            array_map(fn (array $check): array => [$check[0], $check[1] ? 'PASS' : 'FAIL'], $checks),
        );

        if (collect($checks)->contains(fn (array $check): bool => ! $check[1])) {
            $this->error('Sitewell Pixel production check failed.');

            return self::FAILURE;
        }

        $this->info('Sitewell Pixel asset and payload endpoint are ready.');

        return self::SUCCESS;
    }

    private function isPixelAsset(Response $response): bool
    {
        return $response->successful()
            && Str::contains($response->body(), ['__SITEWELL_PIXEL_LOADED__', 'PIXEL_VERSION']);
    }

    private function hasValidPayload(Response $response): bool
    {
        return $response->successful()
            && is_int($response->json('version'))
            && is_string($response->json('url'))
            && is_array($response->json('changes'));
    }

    private function isCacheable(Response $response): bool
    {
        return Str::contains(Str::lower((string) $response->header('Cache-Control')), 'public')
            && filled($response->header('ETag'));
    }
}
