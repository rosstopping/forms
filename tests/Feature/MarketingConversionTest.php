<?php

use App\Events\MarketingConversionRecorded;
use App\Jobs\GenerateWebsiteAudit;
use App\Models\MarketingConversion;
use App\Models\WebsiteAudit;
use App\Services\MarketingAuditResearch;
use App\Services\MarketingAuditScreenshot;
use App\Services\ProspectWebsiteAnalyzer;
use App\Support\MarketingJourney;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('preserves campaign attribution through audit submission without collecting personal details', function (): void {
    Queue::fake();
    config(['services.turnstile.marketing.site_key' => null, 'services.turnstile.marketing.secret_key' => null]);
    $this->get(route('marketing.ppc.doncaster', ['utm_source' => 'google', 'utm_campaign' => 'doncaster', 'gclid' => 'click-123']))->assertSuccessful();
    $this->get(route('marketing.free-site-audit'))->assertSuccessful();
    $this->post(route('marketing.free-site-audit.store'), ['website_url' => 'https://example.com'])->assertRedirect();

    $audit = WebsiteAudit::query()->sole();
    expect($audit->marketing_attribution['first_touch']['gclid'])->toBe('click-123')
        ->and($audit->marketing_attribution['landing_page'])->toBe('seo/doncaster')
        ->and($audit->email)->toBeNull();
    $conversion = MarketingConversion::query()->sole();
    expect($conversion->name)->toBe('audit_submitted')
        ->and($conversion->payload()['is_conversion'])->toBeTrue();
    $this->get(route('marketing.website-audits.show', $audit))->assertSee($conversion->event_id);
    $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful();
    expect(MarketingConversion::query()->count())->toBe(1);
    Queue::assertPushed(GenerateWebsiteAudit::class);
});

it('keeps first touch and replaces the whole last campaign without mixing click identifiers', function (): void {
    $this->get(route('marketing.ppc.local', ['utm_source' => 'google', 'gclid' => 'first-click']));
    $this->get(route('marketing.ppc.managed', ['utm_source' => 'newsletter', 'utm_campaign' => 'autumn']));
    $this->get(route('marketing.free-site-audit'))->assertSessionHas('marketing_attribution', fn (array $value): bool => $value['first_touch']['gclid'] === 'first-click'
        && $value['last_touch'] === ['utm_source' => 'newsletter', 'utm_campaign' => 'autumn']
    );
});

it('ignores malformed attribution and expires old sessions', function (): void {
    $this->withSession(['marketing_attribution' => ['expires_at' => now()->subDay()->timestamp]])
        ->get(route('marketing.ppc.managed', ['gclid' => ['bad'], 'utm_campaign' => str_repeat('x', 256), 'utm_source' => '<script>']))
        ->assertSuccessful()->assertSessionHas('marketing_attribution', fn (array $value): bool => $value['first_touch'] === [] && isset($value['journey_id']));
});

it('does not record submissions that fail validation or honeypot checks', function (): void {
    Queue::fake();
    $this->post(route('marketing.free-site-audit.store'), ['website_url' => 'not valid'])->assertSessionHasErrors();
    $this->post(route('marketing.free-site-audit.store'), ['website_url' => 'https://example.com', '_sitewell_check' => 'bot'])->assertSessionHasErrors();
    expect(MarketingConversion::query()->count())->toBe(0);
});

it('records audit completion once across job retries and does not count it as a lead conversion', function (): void {
    $audit = WebsiteAudit::factory()->create(['marketing_attribution' => ['journey_id' => 'journey-test']]);
    $analyzer = Mockery::mock(ProspectWebsiteAnalyzer::class);
    $analyzer->shouldReceive('analyze')->twice()->andReturn(['score' => 50, 'findings' => [], 'contacts' => []]);
    $research = Mockery::mock(MarketingAuditResearch::class);
    $research->shouldReceive('forAudit')->twice()->andReturn(['health_score' => 50]);
    $screenshot = Mockery::mock(MarketingAuditScreenshot::class);
    $screenshot->shouldReceive('capture')->twice();
    $job = new GenerateWebsiteAudit($audit);
    $job->handle($analyzer, new MarketingJourney, $research, $screenshot);
    $job->handle($analyzer, new MarketingJourney, $research, $screenshot);
    $event = MarketingConversion::query()->sole();
    expect($event->name)->toBe('audit_completed')->and($event->payload()['is_conversion'])->toBeFalse();
});

it('does not record failed audits as completed', function (): void {
    $audit = WebsiteAudit::factory()->create();
    (new GenerateWebsiteAudit($audit))->failed(new RuntimeException('Unavailable'));
    expect(MarketingConversion::query()->count())->toBe(0);
});

it('publishes a server hook only for a newly recorded event', function (): void {
    Event::fake([MarketingConversionRecorded::class]);
    $journey = new MarketingJourney;
    $journey->record('audit_submitted', 'same-audit', []);
    $journey->record('audit_submitted', 'same-audit', []);
    Event::assertDispatchedTimes(MarketingConversionRecorded::class, 1);
});

it('attributes a signed calendar booking without requiring an audit signup and deduplicates webhook retries', function (): void {
    config(['services.cal.webhook_secret' => 'ppc-secret']);
    $this->get(route('marketing.ppc.local', ['gclid' => 'paid-click']));
    $redirect = $this->get(route('marketing.ppc.book'))->assertRedirect();
    $click = MarketingConversion::query()->sole();
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['metadata']['sitewell_booking'])->toBe($click->event_id)
        ->and($click->name)->toBe('book_call_clicked')
        ->and($click->payload()['is_conversion'])->toBeFalse();

    $payload = json_encode(['triggerEvent' => 'BOOKING_CREATED', 'payload' => ['uid' => 'booking-123', 'metadata' => ['sitewell_booking' => $click->event_id]]]);
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CAL_SIGNATURE_256' => hash_hmac('sha256', $payload, 'ppc-secret')];
    $this->call('POST', route('cal.webhook'), server: $server, content: $payload)->assertSuccessful();
    $this->call('POST', route('cal.webhook'), server: $server, content: $payload)->assertSuccessful();
    $booked = MarketingConversion::query()->where('name', 'call_booked')->sole();
    expect($booked->attribution['first_touch']['gclid'])->toBe('paid-click')
        ->and($booked->payload()['is_conversion'])->toBeTrue();
});

it('does not count unsigned calendar requests or reschedules as new bookings', function (): void {
    config(['services.cal.webhook_secret' => 'ppc-secret']);
    $click = MarketingConversion::factory()->create();
    $payload = json_encode(['triggerEvent' => 'BOOKING_CREATED', 'payload' => ['uid' => 'booking-123', 'metadata' => ['sitewell_booking' => $click->event_id]]]);
    $this->call('POST', route('cal.webhook'), server: ['CONTENT_TYPE' => 'application/json'], content: $payload)->assertBadRequest();
    $payload = str_replace('BOOKING_CREATED', 'BOOKING_RESCHEDULED', $payload);
    $this->call('POST', route('cal.webhook'), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CAL_SIGNATURE_256' => hash_hmac('sha256', $payload, 'ppc-secret')], content: $payload)->assertSuccessful();
    expect(MarketingConversion::query()->where('name', 'call_booked')->count())->toBe(0);
});

it('returns an attributed calendar URL for the popup without counting a confirmed booking', function (): void {
    $this->get(route('marketing.ppc.local', ['gclid' => 'popup-click']));
    $response = $this->getJson(route('marketing.ppc.book'))->assertSuccessful();
    parse_str(parse_url($response->json('booking_url'), PHP_URL_QUERY), $query);
    $click = MarketingConversion::query()->sole();
    expect($query['metadata']['sitewell_booking'])->toBe($click->event_id)
        ->and($click->name)->toBe('book_call_clicked')
        ->and($click->attribution['first_touch']['gclid'])->toBe('popup-click')
        ->and($click->payload()['is_conversion'])->toBeFalse();
});
