<?php

use App\Models\Website;

function heartbeatWebsite(): Website
{
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);

    return $website;
}

it('records a valid pixel heartbeat and normalized page sighting', function (): void {
    $website = heartbeatWebsite();

    $this->postJson(route('pixel.heartbeat', $website->pixel_public_key), [
        'url' => 'http://www.example.com/services/?campaign=summer#details',
        'version' => '1.0.0',
    ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', '*');

    $website->refresh();
    expect($website->pixel_last_seen_at)->not->toBeNull()
        ->and($website->pixel_last_seen_hostname)->toBe('example.com')
        ->and($website->pixel_last_seen_url)->toBe('https://example.com/services')
        ->and($website->pixel_version)->toBe('1.0.0')
        ->and($website->pixelPages()->sole()->url)->toBe('https://example.com/services');
});

it('updates a page sighting instead of creating analytics events', function (): void {
    $website = heartbeatWebsite();

    $this->postJson(route('pixel.heartbeat', $website->pixel_public_key), [
        'url' => 'https://example.com/services',
        'version' => '1.0.0',
    ])->assertNoContent();
    $firstSeenAt = $website->pixelPages()->sole()->first_seen_at;

    $this->travel(2)->hours();
    $this->postJson(route('pixel.heartbeat', $website->pixel_public_key), [
        'url' => 'http://www.example.com/services/?again=1',
        'version' => '1.0.1',
    ])->assertNoContent();

    expect($website->pixelPages()->count())->toBe(1)
        ->and($website->pixelPages()->sole()->first_seen_at->equalTo($firstSeenAt))->toBeTrue()
        ->and($website->pixelPages()->sole()->last_seen_at->greaterThan($firstSeenAt))->toBeTrue()
        ->and($website->refresh()->pixel_version)->toBe('1.0.1');
});

it('counts distinct normalized pages', function (): void {
    $website = heartbeatWebsite();

    foreach (['/', '/services', '/contact'] as $path) {
        $this->postJson(route('pixel.heartbeat', $website->pixel_public_key), [
            'url' => 'https://example.com'.$path,
            'version' => '1.0.0',
        ])->assertNoContent();
    }

    expect($website->pixelPages()->count())->toBe(3);
});

it('silently ignores unknown keys wrong domains and disabled pixels', function (): void {
    $website = heartbeatWebsite();

    $this->postJson(route('pixel.heartbeat', 'sw_unknown'), [
        'url' => 'https://example.com/',
        'version' => '1.0.0',
    ])->assertNoContent();
    $this->postJson(route('pixel.heartbeat', $website->pixel_public_key), [
        'url' => 'https://evil-example.com/',
        'version' => '1.0.0',
    ])->assertNoContent();
    $website->update(['pixel_enabled' => false]);
    $this->postJson(route('pixel.heartbeat', $website->pixel_public_key), [
        'url' => 'https://example.com/',
        'version' => '1.0.0',
    ])->assertNoContent();

    expect($website->pixelPages()->count())->toBe(0)
        ->and($website->refresh()->pixel_last_seen_at)->toBeNull();
});

it('validates heartbeat URLs and versions', function (array $data): void {
    $website = heartbeatWebsite();

    $this->postJson(route('pixel.heartbeat', $website->pixel_public_key), $data)
        ->assertUnprocessable();
})->with([
    'missing values' => [[]],
    'unsafe URL' => [['url' => 'javascript:alert(1)', 'version' => '1.0.0']],
    'invalid version' => [['url' => 'https://example.com', 'version' => '<script>']],
]);

it('answers heartbeat cors preflight requests', function (): void {
    $website = heartbeatWebsite();

    $this->call('OPTIONS', route('pixel.heartbeat', $website->pixel_public_key))
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
});
