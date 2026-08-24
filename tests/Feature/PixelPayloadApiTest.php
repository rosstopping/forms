<?php

use App\Enums\OptimisationStatus;
use App\Enums\OptimisationType;
use App\Models\Optimisation;
use App\Models\Website;
use App\Services\OptimisationDeploymentManager;

function pixelWebsite(string $domain = 'example.com'): Website
{
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => $domain, 'is_primary' => true]);

    return $website;
}

function deployedPixelOptimisation(
    Website $website,
    string $url = 'https://example.com/services/roof-repairs',
    string $value = 'Roof Repairs in Doncaster',
): Optimisation {
    $optimisation = Optimisation::factory()->for($website)->create([
        'url' => $url,
        'type' => OptimisationType::Title,
    ]);
    $manager = app(OptimisationDeploymentManager::class);
    $manager->createVersion($optimisation, $value, 'Roof Repairs');
    $manager->approve($optimisation->refresh());
    $manager->deploy($optimisation->refresh());

    return $optimisation->refresh();
}

it('returns only the public deployed pixel payload for a valid site and URL', function (): void {
    $website = pixelWebsite();
    $optimisation = deployedPixelOptimisation($website);

    $response = $this->getJson(route('pixel.payload', [
        'siteKey' => $website->pixel_public_key,
        'url' => 'https://example.com/services/roof-repairs',
    ]));

    $response->assertSuccessful()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('ETag')
        ->assertJsonPath('version', 2)
        ->assertJsonPath('changes.0.id', $optimisation->public_id)
        ->assertJsonPath('changes.0.type', 'title')
        ->assertJsonPath('changes.0.value', 'Roof Repairs in Doncaster')
        ->assertJsonMissingPath('changes.0.selector')
        ->assertJsonMissingPath('changes.0.attribute')
        ->assertJsonMissing(['website_id' => $website->id])
        ->assertJsonMissing(['id' => $optimisation->id]);

    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store')
        ->toContain('max-age=0')
        ->not->toContain('stale-if-error');
});

it('does not reveal whether a site key or hostname was invalid', function (): void {
    $website = pixelWebsite();

    $this->getJson(route('pixel.payload', [
        'siteKey' => 'sw_unknown',
        'url' => 'https://example.com/',
    ]))->assertNotFound();

    $this->getJson(route('pixel.payload', [
        'siteKey' => $website->pixel_public_key,
        'url' => 'https://evil-example.com/',
    ]))->assertNotFound();
});

it('matches scheme www trailing slash query string fragment and unreserved encoding variants', function (): void {
    $website = pixelWebsite();
    deployedPixelOptimisation($website, 'https://example.com/services/~roof-repairs/');

    $this->getJson(route('pixel.payload', [
        'siteKey' => $website->pixel_public_key,
        'url' => 'http://www.example.com/services/%7eroof-repairs?campaign=summer#details',
    ]))->assertSuccessful()->assertJsonCount(1, 'changes');
});

it('does not match a different path or a hostname suffix', function (): void {
    $website = pixelWebsite();
    deployedPixelOptimisation($website, 'https://example.com/services');

    $this->getJson(route('pixel.payload', [
        'siteKey' => $website->pixel_public_key,
        'url' => 'https://example.com/services/roofing',
    ]))->assertSuccessful()->assertJsonCount(0, 'changes');

    $this->getJson(route('pixel.payload', [
        'siteKey' => $website->pixel_public_key,
        'url' => 'https://example.com.evil.test/services',
    ]))->assertNotFound();
});

it('excludes draft approved failed and rolled back optimisations', function (): void {
    $website = pixelWebsite();
    $manager = app(OptimisationDeploymentManager::class);

    foreach ([OptimisationStatus::Draft, OptimisationStatus::PendingApproval, OptimisationStatus::Approved, OptimisationStatus::Failed] as $status) {
        $optimisation = Optimisation::factory()->for($website)->create([
            'url' => 'https://example.com/services',
            'status' => $status,
        ]);
        $manager->createVersion($optimisation, $status->value, 'original');
        $optimisation->update(['status' => $status]);
    }

    $rolledBack = deployedPixelOptimisation($website, 'https://example.com/services');
    $manager->rollback($rolledBack);

    $this->getJson(route('pixel.payload', [
        'siteKey' => $website->pixel_public_key,
        'url' => 'https://example.com/services',
    ]))->assertSuccessful()->assertJsonCount(0, 'changes');
});

it('returns a not modified response when the etag matches', function (): void {
    $website = pixelWebsite();
    deployedPixelOptimisation($website);
    $url = route('pixel.payload', [
        'siteKey' => $website->pixel_public_key,
        'url' => 'https://example.com/services/roof-repairs',
    ]);
    $etag = $this->getJson($url)->headers->get('ETag');

    $this->withHeader('If-None-Match', (string) $etag)
        ->getJson($url)
        ->assertNotModified();
});

it('rejects missing malformed and non-http URLs', function (array $query): void {
    $website = pixelWebsite();

    $this->getJson(route('pixel.payload', ['siteKey' => $website->pixel_public_key] + $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');
})->with([
    'missing' => [[]],
    'malformed' => [['url' => 'not a URL']],
    'javascript' => [['url' => 'javascript:alert(1)']],
]);

it('returns no payload when pixel is disabled for the site', function (): void {
    $website = pixelWebsite();
    $website->update(['pixel_enabled' => false]);

    $this->getJson(route('pixel.payload', [
        'siteKey' => $website->pixel_public_key,
        'url' => 'https://example.com/',
    ]))->assertNotFound();
});
