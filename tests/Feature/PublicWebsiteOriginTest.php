<?php

use App\Jobs\GenerateWebsiteAudit;
use App\Mail\WebsiteAuditReport;
use App\Models\WebsiteAudit;
use App\Services\MarketingTurnstileVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    config(['public_website.url' => 'https://digizu.co.uk']);
    URL::forceScheme('https');
    Http::preventStrayRequests();
    Queue::fake();
});

it('generates public routes on Digizu in a queue context while retaining internal routes', function (): void {
    URL::forceRootUrl('https://sitewell.digizu.co.uk');
    expect(route('marketing.home'))->toBe('https://digizu.co.uk')
        ->and(route('marketing.pricing'))->toBe('https://digizu.co.uk/seo-doncaster')
        ->and(route('marketing.free-site-audit'))->toBe('https://digizu.co.uk/get-started')
        ->and(route('login'))->toBe('https://sitewell.digizu.co.uk/login');
    $audit = WebsiteAudit::factory()->create();
    $mail = new WebsiteAuditReport($audit);
    $link = $mail->content()->with['reportUrl'];
    expect($link)->toStartWith('https://digizu.co.uk/website-audits/')
        ->and(URL::hasValidSignature(Request::create($link)))->toBeTrue();
    $mail->assertSeeInHtml('Digizu')->assertDontSeeInHtml('Sitewell');
    expect($mail->render())->not->toContain('sitewell.digizu.co.uk');
});

it('moves marketing pages without starting research and preserves campaign parameters', function (): void {
    $this->get('https://sitewell.digizu.co.uk/pricing?utm_source=legacy')
        ->assertRedirect('https://digizu.co.uk/seo-doncaster/?utm_source=legacy')
        ->assertStatus(301);
    Queue::assertNothingPushed();
});

it('validates and re-signs legacy report links without extending their expiry', function (): void {
    $audit = WebsiteAudit::factory()->create();
    config(['public_website.url' => null]);
    URL::forceRootUrl('https://sitewell.digizu.co.uk');
    $legacy = URL::temporarySignedRoute('marketing.website-audits.full', now()->addHours(2), $audit);
    config(['public_website.url' => 'https://digizu.co.uk']);
    $response = $this->get($legacy)->assertRedirect();
    $destination = $response->headers->get('Location');
    expect($destination)->toStartWith('https://digizu.co.uk/website-audits/');
    $oldRequest = Request::create($legacy);
    $newRequest = Request::create($destination);
    expect($newRequest->query('expires'))->toBe($oldRequest->query('expires'))
        ->and(URL::hasValidSignature($newRequest))->toBeTrue();
    $this->get($legacy.'&extra=1')->assertForbidden();
    $this->travel(3)->hours();
    $this->get($legacy)->assertForbidden();
    Queue::assertNothingPushed();
});

it('keeps the public app isolated from internal account routes and cookies', function (): void {
    config(['session.domain' => '.digizu.co.uk']);
    $this->get('https://digizu.co.uk/login')->assertNotFound();
    $this->get('https://digizu.co.uk/get-started')->assertSuccessful()->assertSee('Digizu')->assertDontSee('Sitewell');
    expect(config('session.cookie'))->toBe('__Host-digizu_tools')
        ->and(config('session.domain'))->toBeNull()
        ->and(config('session.secure'))->toBeTrue();
});

it('submits a Digizu audit once and redirects to the Digizu report', function (): void {
    $response = $this->from('https://digizu.co.uk/get-started')->post('https://digizu.co.uk/get-started', [
        'website_url' => 'northfield.example', '_sitewell_check' => '',
    ]);
    $audit = WebsiteAudit::query()->sole();
    $response->assertRedirect('https://digizu.co.uk/website-audits/'.$audit->public_id);
    Queue::assertPushed(GenerateWebsiteAudit::class, 1);
});

it('redirects replaced marketing paths on Digizu to the existing page', function (): void {
    $this->get('https://digizu.co.uk/pricing?utm_source=legacy')->assertRedirect('https://digizu.co.uk/seo-doncaster/?utm_source=legacy');
    $this->get('https://digizu.co.uk/wordpress')->assertRedirect('https://digizu.co.uk/contact/');
    Queue::assertNothingPushed();
});

it('publishes a public sitemap with Digizu URLs and no private audit records', function (): void {
    $sitemap = tempnam(sys_get_temp_dir(), 'digizu-static-sitemap-');
    file_put_contents($sitemap, '<urlset><url><loc>https://digizu.co.uk/</loc></url><url><loc>https://digizu.co.uk/services/</loc></url><url><loc>https://digizu.co.uk/seo-doncaster/</loc></url><url><loc>https://digizu.co.uk/seo-for-small-businesses/</loc></url></urlset>');
    config(['public_website.static_sitemap' => $sitemap]);

    $response = $this->get('https://digizu.co.uk/tools-sitemap.xml')->assertSuccessful();
    $response->assertDontSee('sitewell.digizu.co.uk')->assertDontSee('/website-audits/')
        ->assertDontSee('/services<')->assertDontSee('/pay-monthly-websites<')->assertDontSee('/seo-for-small-businesses<');
    unlink($sitemap);
    $xml = simplexml_load_string($response->getContent());
    $urls = array_map(fn ($node): string => (string) $node->loc, iterator_to_array($xml->url));
    expect($urls)->not->toBeEmpty()->and(count($urls))->toBe(count(array_unique($urls)));
});

it('requires a CSRF token for a public audit mutation', function (): void {
    app()->instance('env', 'production');
    $this->post('https://digizu.co.uk/get-started', ['website_url' => 'northfield.example'])
        ->assertStatus(419);
    Queue::assertNothingPushed();
});

it('issues a secure host-only public cookie and hides internal admin routes', function (): void {
    $response = $this->get('https://digizu.co.uk/get-started')->assertSuccessful();
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie): bool => $cookie->getName() === '__Host-digizu_tools');
    expect($cookie)->not->toBeNull()
        ->and($cookie->getDomain())->toBeNull()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getPath())->toBe('/');
    $this->get('https://digizu.co.uk/admin')->assertNotFound();
    $this->get('https://digizu.co.uk/admin/websites/1')->assertNotFound();
});

it('does not expose internal API routes through the public static fallback', function (): void {
    $this->getJson('https://digizu.co.uk/api/agent/v1/websites')->assertNotFound();
    $this->options('https://digizu.co.uk/api/pixel/example/heartbeat')->assertNotFound();
    $this->options('https://sitewell.digizu.co.uk/api/pixel/example/heartbeat')->assertNoContent();
});

it('fails closed if public routing is installed before the origin is enabled', function (): void {
    config(['public_website.url' => null]);
    $this->get('https://digizu.co.uk/get-started')->assertServiceUnavailable();
    $this->get('https://digizu.co.uk/login')->assertServiceUnavailable();
    $this->getJson('https://digizu.co.uk/api/agent/v1/websites')->assertServiceUnavailable();
    Queue::assertNothingPushed();
});

it('migrates the old audit entry directly to the public form', function (): void {
    $this->get('https://sitewell.digizu.co.uk/free-site-audit?utm_source=legacy')
        ->assertRedirect('https://digizu.co.uk/get-started?utm_source=legacy')->assertStatus(301);
    $this->get('https://digizu.co.uk/free-site-audit')->assertRedirect('https://digizu.co.uk/get-started')->assertStatus(301);
});

it('allows signed public unsubscribe confirmation without sending mail or queuing work', function (): void {
    $audit = WebsiteAudit::factory()->create(['email' => 'owner@example.com', 'marketing_consent_at' => now()]);
    $url = URL::signedRoute('marketing.website-audits.email-preferences', $audit);
    $this->get($url)->assertSuccessful();
    expect($audit->refresh()->marketing_consent_at)->not->toBeNull();
    $this->post($url)->assertRedirect();
    expect($audit->refresh()->marketing_consent_at)->toBeNull()
        ->and($audit->marketing_consent_withdrawn_at)->not->toBeNull();
    Queue::assertNothingPushed();
});

it('verifies the configured public Turnstile host while preserving the internal host restriction', function (): void {
    config([
        'services.turnstile.marketing.enabled' => true,
        'services.turnstile.marketing.site_key' => 'test',
        'services.turnstile.marketing.secret_key' => 'test',
        'services.turnstile.marketing.hostname' => 'sitewell.digizu.co.uk',
    ]);
    Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true, 'hostname' => 'digizu.co.uk'])]);
    $verifier = app(MarketingTurnstileVerifier::class);
    expect($verifier->passes('token', '127.0.0.1', 'digizu.co.uk'))->toBeTrue()
        ->and($verifier->passes('token', '127.0.0.1', 'sitewell.digizu.co.uk'))->toBeFalse()
        ->and($verifier->passes('token', '127.0.0.1', 'other.example'))->toBeFalse();
});

it('keeps the public enquiry endpoint preflight available', function (): void {
    $this->options('https://digizu.co.uk/submit')->assertNoContent()->assertHeader('Access-Control-Allow-Origin', '*');
});

it('brands public missing pages as Digizu without indexing them', function (): void {
    $this->get('https://digizu.co.uk/no-such-public-page')->assertNotFound()
        ->assertSee('Digizu')->assertDontSee('Sitewell')->assertSee('noindex, nofollow');
});

it('returns homepage audit validation as JSON only when requested', function (): void {
    $this->postJson('https://digizu.co.uk/get-started', ['website_url' => 'invalid'])
        ->assertUnprocessable()->assertJsonValidationErrors('website_url');
    $this->from('https://digizu.co.uk/get-started')->post('https://digizu.co.uk/get-started', ['website_url' => 'invalid'])
        ->assertRedirect('https://digizu.co.uk/get-started')->assertSessionHasErrors('website_url');
    Queue::assertNothingPushed();
});
