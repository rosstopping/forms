<?php

use App\Models\BusinessProfileConnection;
use App\Models\SeoSnapshot;
use App\Models\User;
use App\Models\Website;
use App\Services\BusinessProfileClient;
use App\Support\WebsiteNavigation;
use Illuminate\Support\Facades\Queue;

it('renders the requested website section without client-side tab state', function (string $section): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    $response = $this->actingAs($admin)->get(WebsiteNavigation::routeFor($website, $section))->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $panels = $xpath->query('//div[starts-with(@id, "website-panel-") and not(@hidden)]');
    expect($panels->length)->toBe(1)
        ->and($panels->item(0)->getAttribute('id'))->toBe('website-panel-'.$section);
    expect($xpath->query('//*[@data-website-sections and @data-tabs]')->length)->toBe(0);
    $link = $xpath->query('//*[@id="website-tab-'.$section.'"]')->item(0);
    expect($link->tagName)->toBe('a')
        ->and($link->getAttribute('href'))->toBe(WebsiteNavigation::routeFor($website, $section));
})->with(['health', 'content', 'search', 'seo', 'forms', 'settings', 'business-profile']);

it('returns invalid content submissions to Content with errors and input', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    $url = WebsiteNavigation::routeFor($website, 'content');
    $this->actingAs($admin)->from($url)->post(route('admin.content-requests.store', $website), ['instructions' => ''])
        ->assertRedirect($url)->assertSessionHasErrors('instructions');
    $this->get($url)->assertSuccessful();
});

it('uses URL-based SEO links and renders the selected SEO section', function (string $section): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    SeoSnapshot::factory()->for($website)->create();
    $url = route('admin.websites.section', [$website, 'seo', 'seo_section' => $section]);
    $response = $this->actingAs($admin)->get($url)->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $panels = $xpath->query('//div[starts-with(@id, "seo-section-panel-") and not(@hidden)]');
    expect($panels->length)->toBe(1)
        ->and($panels->item(0)->getAttribute('id'))->toBe('seo-section-panel-'.$section);
    $link = $xpath->query('//*[@id="seo-section-tab-'.$section.'"]')->item(0);
    expect($link->tagName)->toBe('a')->and($link->getAttribute('href'))->toBe($url)
        ->and($link->getAttribute('aria-current'))->toBe('page');
})->with(['overview', 'targets', 'actions', 'keywords', 'backlinks', 'competitors']);

it('returns to Business Profile after selecting a Google location', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    BusinessProfileConnection::factory()->for($website)->create();
    $this->mock(BusinessProfileClient::class)->shouldReceive('locations')->once()
        ->andReturn([['name' => 'locations/123', 'title' => 'Local business']]);
    $this->actingAs($owner)->post(route('admin.business-profile.location.store', $website), [
        'account_name' => 'accounts/123', 'location_name' => 'locations/123', 'location_title' => 'Local business',
    ])->assertRedirect(WebsiteNavigation::routeFor($website, 'business-profile'));
});
