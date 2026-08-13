<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('passes when the production asset and payload endpoint are ready', function () {
    Http::fake([
        'https://cdn.sitewell.test/pixel.js' => Http::response(
            'var PIXEL_VERSION = "1.0.0"; window.__SITEWELL_PIXEL_LOADED__ = true;',
            headers: ['Content-Type' => 'application/javascript'],
        ),
        'https://api.sitewell.test/api/pixel/*' => Http::response([
            'version' => 12,
            'url' => 'example.com/services',
            'changes' => [],
        ], headers: [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=60',
            'ETag' => '"pixel-12"',
        ]),
    ]);

    $this->artisan('sitewell:pixel:check', [
        'site-key' => 'sw_abcdefghijklmnopqrstuvwxyz',
        'url' => 'https://example.com/services?campaign=test',
        '--asset-url' => 'https://cdn.sitewell.test/pixel.js',
        '--api-url' => 'https://api.sitewell.test/api/pixel',
    ])
        ->expectsOutputToContain('Sitewell Pixel asset and payload endpoint are ready.')
        ->assertSuccessful();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.sitewell.test/api/pixel/sw_abcdefghijklmnopqrstuvwxyz?url=https%3A%2F%2Fexample.com%2Fservices%3Fcampaign%3Dtest');
});

it('fails when the payload cannot be served for the customer URL', function () {
    Http::fake([
        'https://cdn.sitewell.test/pixel.js' => Http::response(
            'var PIXEL_VERSION = "1.0.0"; window.__SITEWELL_PIXEL_LOADED__ = true;',
        ),
        'https://api.sitewell.test/api/pixel/*' => Http::response([], 404),
    ]);

    $this->artisan('sitewell:pixel:check', [
        'site-key' => 'sw_abcdefghijklmnopqrstuvwxyz',
        'url' => 'https://wrong.example/services',
        '--asset-url' => 'https://cdn.sitewell.test/pixel.js',
        '--api-url' => 'https://api.sitewell.test/api/pixel',
    ])
        ->expectsOutputToContain('Sitewell Pixel production check failed.')
        ->assertFailed();
});

it('rejects invalid public keys and page URLs before making requests', function (array $arguments) {
    $this->artisan('sitewell:pixel:check', $arguments)->assertExitCode(2);

    Http::assertNothingSent();
})->with([
    'invalid key' => [[
        'site-key' => '123',
        'url' => 'https://example.com',
    ]],
    'invalid URL' => [[
        'site-key' => 'sw_abcdefghijklmnopqrstuvwxyz',
        'url' => 'javascript:alert(1)',
    ]],
]);
