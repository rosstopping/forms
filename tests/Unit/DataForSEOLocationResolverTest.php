<?php

use App\Services\DataForSEO\Data\DataForSEOResponse;
use App\Services\DataForSEO\DataForSEOClient;
use App\Services\DataForSEO\DataForSEOLocationResolver;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

it('resolves a short UK place name to a cached DataForSEO location code', function (): void {
    Cache::flush();
    $client = Mockery::mock(DataForSEOClient::class);
    $client->shouldReceive('get')->once()->with(DataForSEOLocationResolver::ENDPOINT)->andReturn(new DataForSEOResponse(
        DataForSEOLocationResolver::ENDPOINT,
        [
            ['location_code' => 1006886, 'location_name' => 'Doncaster,England,United Kingdom', 'country_iso_code' => 'GB', 'location_type' => 'City'],
            ['location_code' => 1006887, 'location_name' => 'Doncaster,South Yorkshire,England,United Kingdom', 'country_iso_code' => 'GB', 'location_type' => 'Municipality'],
        ],
        0,
        2,
        null,
    ));

    $resolver = new DataForSEOLocationResolver($client);

    expect($resolver->resolve('doncaster'))->toBe(1006886)
        ->and($resolver->resolve('Doncaster'))->toBe(1006886);
});

it('returns an actionable error for an unsupported UK place', function (): void {
    Cache::flush();
    $client = Mockery::mock(DataForSEOClient::class);
    $client->shouldReceive('get')->once()->andReturn(new DataForSEOResponse(DataForSEOLocationResolver::ENDPOINT, [], 0, 0, null));

    expect(fn () => (new DataForSEOLocationResolver($client))->resolve('Not A Real Place'))
        ->toThrow(InvalidArgumentException::class, 'does not recognise the UK location');
});
