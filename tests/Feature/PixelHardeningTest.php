<?php

use App\Enums\OptimisationStatus;
use App\Models\Optimisation;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Services\OptimisationDeploymentManager;
use App\Services\PixelUrlNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

beforeEach(fn () => config(['forms.pixel_ui_enabled' => true]));

/** @return array{User, Website, WebsiteHealthReport, WebsiteHealthReportPage} */
function hardenedPixelWorkspace(): array
{
    $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $report = WebsiteHealthReport::factory()->for($website)->create();
    $page = WebsiteHealthReportPage::factory()->for($report, 'report')->create([
        'url' => 'https://example.com/services',
        'url_hash' => hash('sha256', 'https://example.com/services'),
    ]);

    return [$owner, $website, $report, $page];
}

function deployHardenedOptimisation(Website $website, WebsiteHealthReportPage $page, string $value): Optimisation
{
    $optimisation = Optimisation::factory()->for($website)->create([
        'website_health_report_page_id' => $page->id,
        'url' => $page->url,
        'type' => 'title',
    ]);
    $manager = app(OptimisationDeploymentManager::class);
    $manager->createVersion($optimisation, $value, 'Original title');
    $manager->approve($optimisation->refresh());
    $manager->deploy($optimisation->refresh());

    return $optimisation->refresh();
}

it('caches payload changes by site version and URL hash', function (): void {
    Cache::flush();
    [, $website, , $page] = hardenedPixelWorkspace();
    deployHardenedOptimisation($website, $page, 'First title');
    $website->refresh();
    $urlHash = app(PixelUrlNormalizer::class)->hash($page->url);
    $cacheKey = "pixel-payload:{$website->id}:{$website->pixel_payload_version}:{$urlHash}";
    $payloadUrl = route('pixel.payload', ['siteKey' => $website->pixel_public_key, 'url' => $page->url]);

    $this->getJson($payloadUrl)->assertSuccessful()->assertJsonCount(1, 'changes');
    expect(Cache::has($cacheKey))->toBeTrue();

    deployHardenedOptimisation($website, $page, 'Second title');
    $this->getJson($payloadUrl)->assertSuccessful()->assertJsonCount(2, 'changes');
});

it('disables and re-enables every pixel change for a website', function (): void {
    [$owner, $website, , $page] = hardenedPixelWorkspace();
    deployHardenedOptimisation($website, $page, 'Live title');
    $payloadUrl = route('pixel.payload', ['siteKey' => $website->pixel_public_key, 'url' => $page->url]);

    $this->actingAs($owner)
        ->put(route('admin.websites.pixel.update', $website), ['pixel_enabled' => false])
        ->assertRedirect();
    $this->getJson($payloadUrl)->assertNotFound();
    expect(Optimisation::query()->sole()->status)->toBe(OptimisationStatus::Deployed);

    $this->actingAs($owner)
        ->put(route('admin.websites.pixel.update', $website), ['pixel_enabled' => true])
        ->assertRedirect();
    $this->getJson($payloadUrl)->assertSuccessful()->assertJsonCount(1, 'changes');
});

it('rotates the public key and resets connection detection', function (): void {
    [$owner, $website, , $page] = hardenedPixelWorkspace();
    $website->forceFill([
        'pixel_last_seen_at' => now(),
        'pixel_last_seen_url' => $page->url,
        'pixel_last_seen_hostname' => 'example.com',
        'pixel_version' => '1.0.0',
    ])->save();
    $oldKey = $website->pixel_public_key;

    $this->actingAs($owner)
        ->post(route('admin.websites.pixel.rotate-key', $website))
        ->assertRedirect();

    $website->refresh();
    expect($website->pixel_public_key)->not->toBe($oldKey)
        ->and($website->pixel_last_seen_at)->toBeNull()
        ->and($website->pixel_version)->toBeNull();
    $this->getJson(route('pixel.payload', ['siteKey' => $oldKey, 'url' => $page->url]))->assertNotFound();
    $this->getJson(route('pixel.payload', ['siteKey' => $website->pixel_public_key, 'url' => $page->url]))->assertSuccessful();
});

it('rolls back every live pixel optimisation on one page with individual history', function (): void {
    [$owner, $website, $report, $page] = hardenedPixelWorkspace();
    deployHardenedOptimisation($website, $page, 'First title');
    deployHardenedOptimisation($website, $page, 'Second title');

    $this->actingAs($owner)
        ->post(route('admin.optimisations.rollback-page', [$website, $report, $page]))
        ->assertRedirect();

    expect($page->optimisations()->where('status', OptimisationStatus::Deployed)->count())->toBe(0)
        ->and($page->optimisations()->where('status', OptimisationStatus::RolledBack)->count())->toBe(2)
        ->and($page->optimisations()->withCount('deployments')->get()->pluck('deployments_count')->all())->toBe([2, 2]);
});

it('throttles rejected-request observability without logging raw public input', function (): void {
    Log::spy();
    $url = route('pixel.payload', [
        'siteKey' => 'sw_unknown_public_key_value',
        'url' => 'https://attacker.example/private?token=secret',
    ]);

    $this->getJson($url)->assertNotFound();
    $this->getJson($url)->assertNotFound();

    Log::shouldHaveReceived('notice')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'Pixel public request rejected.'
                && $context['reason'] === 'unknown_or_disabled_site'
                && ! in_array('sw_unknown_public_key_value', $context, true)
                && ! in_array('https://attacker.example/private?token=secret', $context, true);
        });
});

it('shows managers the site and page emergency controls', function (): void {
    [$owner, $website, $report, $page] = hardenedPixelWorkspace();
    deployHardenedOptimisation($website, $page, 'Live title');

    $this->actingAs($owner)
        ->get(route('admin.websites.show', ['website' => $website, 'tab' => 'pixel']))
        ->assertSuccessful()
        ->assertSee('Disable all Pixel changes')
        ->assertSee('Rotate public key');
    $this->actingAs($owner)
        ->get(route('admin.website-health-report-pages.show', [$website, $report, $page]))
        ->assertSuccessful()
        ->assertSee('Rollback all on page');
});
