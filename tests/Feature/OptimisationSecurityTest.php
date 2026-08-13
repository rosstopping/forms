<?php

use App\Enums\OptimisationStatus;
use App\Models\Optimisation;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Services\OptimisationDeploymentManager;

/** @return array{User, Website, WebsiteHealthReport, WebsiteHealthReportPage} */
function optimisationSecurityWorkspace(): array
{
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $report = WebsiteHealthReport::factory()->for($website)->create();
    $page = WebsiteHealthReportPage::factory()->for($report, 'report')->create([
        'url' => 'https://example.com/services',
        'url_hash' => hash('sha256', 'https://example.com/services'),
    ]);

    return [$owner, $website, $report, $page];
}

it('rejects active HTML and executable attributes', function (string $value): void {
    [$owner, $website, $report, $page] = optimisationSecurityWorkspace();

    $this->actingAs($owner)
        ->from(route('admin.website-health-report-pages.show', [$website, $report, $page]))
        ->post(route('admin.optimisations.store', [$website, $report, $page]), [
            'type' => 'html',
            'selector' => '#intro',
            'new_value' => $value,
        ])
        ->assertSessionHasErrors('new_value');

    expect(Optimisation::query()->count())->toBe(0);
})->with([
    'script element' => '<p>Safe</p><script>alert(1)</script>',
    'event attribute' => '<p onclick="alert(1)">Unsafe</p>',
    'inline style' => '<p style="background:url(javascript:alert(1))">Unsafe</p>',
    'svg' => '<svg onload="alert(1)"></svg>',
    'iframe' => '<iframe src="https://attacker.example"></iframe>',
]);

it('sanitizes harmless unsupported HTML and strips unapproved attributes', function (): void {
    [$owner, $website, $report, $page] = optimisationSecurityWorkspace();

    $this->actingAs($owner)->post(route('admin.optimisations.store', [$website, $report, $page]), [
        'type' => 'html',
        'selector' => '#intro',
        'new_value' => '<article data-secret="value"><p class="lead" title="Introduction">Safe content</p></article>',
    ])->assertRedirect();

    expect(Optimisation::query()->sole()->currentVersion->new_value)
        ->toBe('<p title="Introduction">Safe content</p>');
});

it('rejects invalid JSON-LD and canonicalizes valid structured data', function (): void {
    [$owner, $website, $report, $page] = optimisationSecurityWorkspace();

    $this->actingAs($owner)->post(route('admin.optimisations.store', [$website, $report, $page]), [
        'type' => 'json_ld',
        'new_value' => '{invalid json',
    ])->assertSessionHasErrors('new_value');

    $this->actingAs($owner)->post(route('admin.optimisations.store', [$website, $report, $page]), [
        'type' => 'json_ld',
        'new_value' => "{\n  \"@context\": \"https://schema.org\", \"@type\": \"LocalBusiness\"\n}",
    ])->assertRedirect();

    expect(Optimisation::query()->sole()->currentVersion->new_value)
        ->toBe('{"@context":"https://schema.org","@type":"LocalBusiness"}');
});

it('rejects dangerous or unsupported attributes and external internal links', function (array $data): void {
    [$owner, $website, $report, $page] = optimisationSecurityWorkspace();

    $this->actingAs($owner)
        ->post(route('admin.optimisations.store', [$website, $report, $page]), $data)
        ->assertSessionHasErrors();

    expect(Optimisation::query()->count())->toBe(0);
})->with([
    'event attribute' => [[
        'type' => 'attribute', 'selector' => '#cta', 'attribute' => 'onclick', 'new_value' => 'alert(1)',
    ]],
    'javascript href' => [[
        'type' => 'attribute', 'selector' => '#cta', 'attribute' => 'href', 'new_value' => 'javascript:alert(1)',
    ]],
    'external internal link' => [[
        'type' => 'internal_link', 'selector' => '#cta', 'new_value' => 'https://attacker.example/page',
    ]],
]);

it('refuses deployment when unsafe content bypassed the HTTP validation layer', function (): void {
    [, $website, , $page] = optimisationSecurityWorkspace();
    $optimisation = Optimisation::factory()->for($website)->create([
        'website_health_report_page_id' => $page->id,
        'url' => $page->url,
        'type' => 'html',
        'selector' => '#intro',
    ]);
    $optimisation->versions()->create([
        'version' => 1,
        'original_value' => '<p>Original</p>',
        'new_value' => '<img src=x onerror="alert(1)">',
    ]);
    $manager = app(OptimisationDeploymentManager::class);
    $manager->approve($optimisation);

    $deployment = $manager->deploy($optimisation->refresh());

    expect($deployment->status->value)->toBe('failed')
        ->and($optimisation->refresh()->status)->toBe(OptimisationStatus::Failed);
});

it('rejects selectors containing control characters', function (): void {
    [$owner, $website, $report, $page] = optimisationSecurityWorkspace();

    $this->actingAs($owner)->post(route('admin.optimisations.store', [$website, $report, $page]), [
        'type' => 'text',
        'selector' => "#intro\0hidden",
        'new_value' => 'Safe text',
    ])->assertSessionHasErrors('selector');

    expect(Optimisation::query()->count())->toBe(0);
});
