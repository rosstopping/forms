<?php

use App\Models\FormSubmission;
use App\Models\MarketingConversion;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Mail::fake();
    $website = Website::factory()->create();
    config(['marketing.contact_website_id' => $website->id]);
    $this->enquiry = ['name' => 'Alex Example', 'email' => 'alex@example.test', 'agency' => 'Example Business', 'goals' => 'Please manage our website and SEO.'];
});

it('records a genuine enquiry only after saving it and publishes success once without personal data in the tag payload', function (): void {
    $this->get(route('marketing.home', ['gclid' => 'paid-click-123', 'utm_campaign' => 'managed-seo']))->assertSuccessful();
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->from(route('marketing.contact'))->post(route('marketing.contact.store'), $this->enquiry)->assertRedirect(route('marketing.contact'))->assertSessionHas('marketing_enquiry_event');
    $saved = FormSubmission::query()->sole();
    $event = MarketingConversion::query()->sole();
    expect($event->name)->toBe('enquiry_submitted')
        ->and($event->payload()['is_conversion'])->toBeTrue()
        ->and($event->attribution['form_submission_id'])->toBe($saved->id)
        ->and($event->attribution['first_touch']['gclid'])->toBe('paid-click-123')
        ->and(json_encode($event->payload()))->not->toContain('alex@example.test', 'Alex Example', 'Please manage');
    $this->get(route('marketing.contact'))->assertSuccessful()->assertSee('data-marketing-events', false)->assertSee('marketing-events')->assertSee($event->event_id)->assertSee('<link rel="canonical" href="'.route('marketing.contact').'">', false);
    $this->get(route('marketing.contact'))->assertSuccessful()->assertDontSee($event->event_id);
    Mail::assertQueuedCount(1);
});

it('deduplicates retried identical submissions and their notification and advertising event', function (): void {
    $this->post(route('marketing.contact.store'), $this->enquiry)->assertRedirect();
    $event = MarketingConversion::query()->sole();
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->get(route('marketing.contact'))->assertSuccessful()->assertSee($event->event_id);
    $this->post(route('marketing.contact.store'), $this->enquiry)->assertRedirect()->assertSessionMissing('marketing_enquiry_event');
    $this->get(route('marketing.contact'))->assertSuccessful()->assertDontSee($event->event_id);
    $this->assertDatabaseCount('form_submissions', 1);
    $this->assertDatabaseCount('marketing_conversions', 1);
    Mail::assertQueuedCount(1);
});

it('does not confuse different enquiries in one session', function (): void {
    $this->post(route('marketing.contact.store'), $this->enquiry)->assertRedirect();
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->post(route('marketing.contact.store'), [...$this->enquiry, 'goals' => 'A different request about another website.'])->assertRedirect();
    $this->assertDatabaseCount('form_submissions', 2);
    expect(MarketingConversion::query()->where('name', 'enquiry_submitted')->count())->toBe(2);
});

it('never creates an enquiry conversion for invalid or trapped submissions', function (array $overrides): void {
    $this->post(route('marketing.contact.store'), [...$this->enquiry, ...$overrides])->assertSessionHasErrors()->assertSessionMissing('marketing_enquiry_event');
    $this->assertDatabaseCount('form_submissions', 0);
    $this->assertDatabaseCount('marketing_conversions', 0);
    Mail::assertNothingOutgoing();
})->with([
    [['email' => 'invalid']], [['goals' => '']], [['_sitewell_check' => 'bot']], [['_honeypot' => 'bot']],
]);

it('rolls back the saved submission if recording its success fails and emits no browser success', function (): void {
    $this->withoutExceptionHandling();
    Event::listen('eloquent.creating: '.MarketingConversion::class, function (): void {
        throw new RuntimeException('Database unavailable');
    });
    try {
        expect(fn () => $this->post(route('marketing.contact.store'), $this->enquiry))->toThrow(RuntimeException::class, 'Database unavailable');
        $this->assertDatabaseCount('form_submissions', 0);
        $this->assertDatabaseCount('marketing_conversions', 0);
        expect(session()->has('marketing_enquiry_event'))->toBeFalse();
        Mail::assertNothingOutgoing();
    } finally {
        Event::forget('eloquent.creating: '.MarketingConversion::class);
    }
});

it('does not emit advertising conversions for staff tests or page visits', function (): void {
    $this->get(route('marketing.contact', ['status' => 'success']))->assertSuccessful()->assertDontSee('data-marketing-events', false);
    $this->assertDatabaseCount('marketing_conversions', 0);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->post(route('marketing.contact.store'), $this->enquiry)->assertRedirect()->assertSessionMissing('marketing_enquiry_event');
    expect(MarketingConversion::query()->sole()->payload()['is_conversion'])->toBeFalse();
});

it('counts saved agency enquiries through the same success path and deduplicates retries', function (): void {
    $data = [...$this->enquiry, 'website' => 'https://example.test', 'client_websites' => 3, 'offers_seo' => 'no'];
    $this->post(route('marketing.agencies.store'), $data)->assertRedirect()->assertSessionHas('marketing_enquiry_event');
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->get(route('marketing.agencies'))->assertSuccessful();
    $this->post(route('marketing.agencies.store'), $data)->assertRedirect()->assertSessionMissing('marketing_enquiry_event');
    $this->assertDatabaseCount('form_submissions', 1);
    $this->assertDatabaseCount('marketing_conversions', 1);
    Mail::assertQueuedCount(1);
});

it('accepts an intentional new enquiry with identical wording from a newly rendered form', function (): void {
    $this->get(route('marketing.contact'))->assertSuccessful()->assertSee('name="_enquiry_id"', false);
    $firstId = (string) Str::uuid();
    $secondId = (string) Str::uuid();
    $this->post(route('marketing.contact.store'), [...$this->enquiry, '_enquiry_id' => $firstId])->assertRedirect();
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->post(route('marketing.contact.store'), [...$this->enquiry, '_enquiry_id' => $firstId])->assertRedirect();
    $this->assertDatabaseCount('form_submissions', 1);
    $this->post(route('marketing.contact.store'), [...$this->enquiry, '_enquiry_id' => $secondId])->assertRedirect();
    $this->assertDatabaseCount('form_submissions', 2);
    $this->assertDatabaseCount('marketing_conversions', 2);
    Mail::assertQueuedCount(2);
});
