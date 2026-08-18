<?php

use App\Services\DataForSEO\Data\DataForSEOResponse;
use App\Services\DataForSEO\DataForSEOClient;
use App\Services\DataForSEO\DataForSEOLocationResolver;
use App\Services\DataForSEO\DataForSEOSerpProvider;

it('maps DataForSEO organic results into the provider-neutral response', function () {
    config()->set('services.dataforseo.language_code', 'en');
    $client = Mockery::mock(DataForSEOClient::class);
    $locations = Mockery::mock(DataForSEOLocationResolver::class);
    $locations->shouldReceive('resolve')->once()->with('Barnsley')->andReturn(1006787);
    $client->shouldReceive('post')->once()->with(DataForSEOSerpProvider::ENDPOINT, Mockery::on(fn (array $task): bool => $task === [
        'keyword' => 'roofer barnsley',
        'location_code' => 1006787,
        'language_code' => 'en',
        'device' => 'desktop',
        'depth' => 100,
    ]))->andReturn(new DataForSEOResponse(DataForSEOSerpProvider::ENDPOINT, [[
        'items' => [
            ['type' => 'organic', 'rank_group' => 38, 'url' => 'https://acme.example/roofing', 'domain' => 'acme.example', 'title' => 'Acme Roofing', 'description' => 'Roofing in Barnsley', 'website_name' => 'Acme Roofing Ltd'],
            ['type' => 'paid', 'rank_group' => 1, 'url' => 'https://ads.example', 'domain' => 'ads.example'],
        ],
    ]], 0.02, 2, 'task-123'));

    $response = (new DataForSEOSerpProvider($client, $locations))->search('roofer barnsley', 'Barnsley');

    expect($response->provider)->toBe('dataforseo')
        ->and($response->cost)->toBe(0.02)
        ->and($response->results)->toHaveCount(1)
        ->and($response->results->first()->position)->toBe(38)
        ->and($response->results->first()->websiteName)->toBe('Acme Roofing Ltd');
});
