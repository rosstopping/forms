<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RestrictCustomerWorkspace;
use App\Models\FormSubmission;
use App\Models\LeadTag;
use App\Models\SearchConsoleConnection;
use App\Models\User;
use App\Models\Website;
use App\Models\WeeklyReport;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    Mail::fake();
    $this->customer = User::factory()->create(['membership_tier' => 'complete', 'membership_status' => 'active']);
    $this->website = Website::factory()->for($this->customer, 'owner')->create(['name' => 'Client site', 'wordpress_enabled' => true]);
    $this->customer->update(['current_website_id' => $this->website->id]);
    $this->lead = FormSubmission::factory()->for($this->website)->create(['data' => ['name' => 'Client enquiry', 'email' => 'lead@example.com']]);
    $this->actingAs($this->customer);
});

test('customers only see portal navigation and overview has no operational links or trial actions', function (): void {
    $this->customer->update(['onboarding_status' => 'trial_active', 'onboarding_trial_ends_at' => now()->addDays(10)]);
    SearchConsoleConnection::factory()->for($this->website)->create(['access_denied_at' => now()]);
    $response = $this->get(route('admin.dashboard'))->assertSuccessful()->assertViewIs('admin.customer-overview')
        ->assertSee('Overview')->assertSee('Leads')->assertSee('Billing')->assertSee('Weekly Overview')->assertSee('Last 28 days');
    foreach (['health', 'search', 'seo', 'ai-visibility', 'content', 'wordpress', 'pixel', 'business-profile', 'forms', 'settings'] as $section) {
        $response->assertDontSee('href="'.route('admin.websites.section', [$this->website, $section]).'"', false);
    }
    $response->assertDontSee('Reconnect Google')->assertDontSee('Book your free call')->assertDontSee('Add website')
        ->assertDontSee('href="'.route('admin.google-ads.index', $this->website).'"', false);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-website-navigation]')->length)->toBe(0);
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

test('customer routes reject staff operations even with historical Complete and manager permissions', function (string $method, string $name, array $parameters): void {
    $parameters = array_map(fn ($parameter) => $parameter === 'website' ? $this->website : $parameter, $parameters);
    $this->call($method, route($name, $parameters), ['enabled' => true, 'tier' => 'growth'])->assertForbidden();
    Http::assertNothingSent();
    Queue::assertNothingPushed();
})->with([
    ['GET', 'admin.websites.index', []],
    ['GET', 'admin.websites.show', ['website']],
    ['GET', 'admin.websites.section', ['website', 'search']],
    ['GET', 'admin.websites.section', ['website', 'settings']],
    ['GET', 'admin.ai-visibility.index', ['website']],
    ['GET', 'admin.google-ads.index', ['website']],
    ['GET', 'admin.forms.index', []],
    ['GET', 'admin.website-setup.create', []],
    ['GET', 'admin.search-console.connect', ['website']],
    ['GET', 'admin.search-console.callback', []],
    ['GET', 'admin.github.callback', []],
    ['GET', 'admin.google-ads.callback', []],
    ['GET', 'admin.business-profile.callback', []],
    ['POST', 'admin.website-health-reports.store', ['website']],
    ['PUT', 'admin.websites.pixel.update', ['website']],
    ['POST', 'admin.content-requests.store', ['website']],
    ['POST', 'admin.content-generations.store', ['website']],
    ['POST', 'admin.seo-target-keywords.check-all', ['website']],
    ['POST', 'admin.business-profile.audits.store', ['website']],
    ['PUT', 'admin.websites.update', ['website']],
    ['DELETE', 'admin.websites.destroy', ['website']],
    ['POST', 'admin.billing.checkout', []],
    ['GET', 'admin.overview', []],
]);

test('all authenticated admin routes carry the central customer boundary', function (): void {
    foreach (Route::getRoutes() as $route) {
        if (str_starts_with($route->getName() ?? '', 'admin.') && in_array('auth', $route->gatherMiddleware(), true)) {
            if ($route->getName() === 'admin.content-suggestions.store') {
                expect($route->gatherMiddleware())->toContain(EnsureAdmin::class);
            } else {
                expect($route->gatherMiddleware())->toContain(RestrictCustomerWorkspace::class);
            }
        }
    }
});

test('legacy signed content queue links cannot grant customers operational access', function (): void {
    $this->get(URL::temporarySignedRoute('admin.content-suggestions.store', now()->addHour(), ['website' => $this->website]))->assertForbidden();
    Queue::assertNothingPushed();
});

test('customers can manage their lead status tags and notes including legacy viewer assignments', function (): void {
    $owner = User::factory()->create();
    $site = Website::factory()->for($owner, 'owner')->create();
    $site->members()->attach($this->customer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $lead = FormSubmission::factory()->for($site)->create();
    $this->get(route('admin.form-submissions.show', $lead))->assertSuccessful()->assertViewIs('admin.form-submissions.customer-show')->assertSee('Save lead')
        ->assertDontSee('Resend notification')->assertDontSee('Review invitation')->assertDontSee('Delete lead');
    $this->put(route('admin.form-submissions.update', $lead), ['status' => 'contacted', 'notes' => 'Called them', 'new_tag' => 'Hot enquiry', 'return_to' => '//external.example'])->assertRedirect(route('admin.form-submissions.show', $lead));
    expect($lead->fresh()->status)->toBe('contacted')->and($lead->fresh()->notes)->toBe('Called them')
        ->and($lead->tags()->sole()->website_id)->toBe($site->id);
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
});

test('customer leads stay within the selected assigned site and other sites cannot be read or updated', function (): void {
    $foreign = FormSubmission::factory()->create(['data' => ['name' => 'Private other enquiry']]);
    $this->get(route('admin.form-submissions.index'))->assertSuccessful()->assertSee('Client enquiry')->assertDontSee('Private other enquiry')->assertDontSee('Add lead');
    $this->get(route('admin.form-submissions.show', $foreign))->assertForbidden();
    $this->put(route('admin.form-submissions.update', $foreign), ['status' => 'won'])->assertForbidden();
    $foreignTag = LeadTag::factory()->for($foreign->website)->create();
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'won', 'tag_ids' => [$foreignTag->id]])->assertSessionHasErrors('tag_ids.0');
    expect($this->lead->fresh()->status)->toBe('new');
});

test('customer lead requests reject operational changes and empty protected inputs cannot clear assignments', function (): void {
    $this->lead->update(['assigned_to' => $this->customer->id, 'follow_up_at' => now()->addDay()]);
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'won', 'assigned_to' => $this->customer->id])->assertSessionHasErrors('assigned_to');
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'won', 'follow_up_at' => now()->addDay()])->assertSessionHasErrors('follow_up_at');
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'contacted', 'assigned_to' => null, 'follow_up_at' => null])->assertRedirect();
    expect($this->lead->fresh()->assigned_to)->toBe($this->customer->id)->and($this->lead->fresh()->follow_up_at)->not->toBeNull();
    $this->delete(route('admin.form-submissions.destroy', $this->lead))->assertForbidden();
    $this->post(route('admin.form-submissions.resend-notification', $this->lead))->assertForbidden();
    $this->patch(route('admin.form-submissions.bulk'), ['selection_scope' => 'all', 'action' => 'delete'])->assertForbidden();
    expect($this->lead->fresh())->not->toBeNull();
});

test('customer website switches preserve portal destinations and reject unassigned sites', function (): void {
    $site = Website::factory()->create();
    $site->members()->attach($this->customer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    foreach (['overview' => 'admin.dashboard', 'leads' => 'admin.form-submissions.index', 'billing' => 'admin.billing.index', 'search' => 'admin.dashboard'] as $section => $destination) {
        $this->post(route('admin.current-website.update'), ['website_id' => $site->id, 'section' => $section])->assertRedirect(route($destination));
    }
    $unassigned = Website::factory()->create();
    $this->post(route('admin.current-website.update'), ['website_id' => $unassigned->id])->assertNotFound();
    expect($this->customer->fresh()->current_website_id)->toBe($site->id);
});

test('customer weekly history reads saved reports without linking to staff work or another site', function (): void {
    $report = WeeklyReport::factory()->for($this->website)->create([
        'snapshot' => ['sections' => [['title' => 'Search performance', 'summary' => 'Recorded progress', 'url' => route('admin.websites.section', [$this->website, 'search'])]],
            'completed_work' => [['title' => 'Improved the services page', 'url' => 'https://github.com/example/private/pull/1']]],
        'overview' => 'Your search visibility improved this week.', 'generated_at' => now(),
    ]);
    $foreign = WeeklyReport::factory()->create(['generated_at' => now()]);
    $this->get(route('admin.weekly-overviews.show', [$this->website, 'weekly_report' => $report->id]))->assertSuccessful()
        ->assertSee('Your search visibility improved this week.')->assertSee('Improved the services page')
        ->assertDontSee('https://github.com/example/private/pull/1')->assertDontSee('View current details');
    $this->get(route('admin.weekly-overviews.show', [$this->website, 'weekly_report' => $foreign->id]))->assertNotFound();
    $this->get(route('admin.weekly-overviews.show', $foreign->website))->assertForbidden();
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

test('customer billing keeps account access and agreed service without self service package choices', function (): void {
    $this->customer->update(['stripe_customer_id' => 'cus_existing']);
    $this->website->update(['service_package' => 'complete', 'service_status' => 'active']);
    $this->get(route('admin.billing.index'))->assertSuccessful()->assertViewIs('account.customer-billing')
        ->assertSee('View invoices and payment details')->assertSee('Complete')->assertDontSee('Most popular')->assertDontSee('Choose Growth')
        ->assertDontSee('href="'.route('admin.billing.checkout').'"', false);
    $this->get(route('admin.profile.edit'))->assertSuccessful();
    Http::assertNothingSent();
});

test('impersonation uses the customer boundary and still allows returning to admin', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)->post(route('admin.users.impersonate.store', $this->customer))->assertRedirect(route('admin.dashboard'));
    $this->get(route('admin.dashboard'))->assertSuccessful()->assertViewIs('admin.customer-overview')->assertSee('Return to admin')->assertDontSee('data-website-navigation>', false);
    $this->delete(route('admin.search-console.destroy', $this->website))->assertForbidden();
    $this->from(route('admin.billing.index'))->post(route('admin.billing.portal'))->assertRedirect(route('admin.billing.index'));
    $this->delete(route('admin.impersonation.destroy'))->assertRedirect(route('admin.users.index'));
    $this->assertAuthenticatedAs($admin);
});

test('admins retain the full workspace and customers without sites still have overview billing and profile', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'current_website_id' => $this->website->id]);
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertSuccessful()->assertViewIs('admin.dashboard')->assertSee('data-website-navigation', false);
    $this->get(route('admin.websites.section', [$this->website, 'settings']))->assertSuccessful();
    $unassigned = User::factory()->create();
    $this->actingAs($unassigned)->get(route('admin.dashboard'))->assertSuccessful()->assertSee('No website is assigned');
    $this->get(route('admin.billing.index'))->assertSuccessful();
    $this->get(route('admin.profile.edit'))->assertSuccessful();
});

test('customer validation feedback renders safely after malformed lead input', function (): void {
    $this->from(route('admin.form-submissions.show', $this->lead))->put(route('admin.form-submissions.update', $this->lead), [
        'status' => 'new', 'notes' => ['invalid'], 'new_tag' => ['invalid'], 'tag_ids' => 'invalid',
    ])->assertSessionHasErrors(['notes', 'new_tag', 'tag_ids']);
    $errors = new ViewErrorBag;
    $errors->put('default', new MessageBag(['notes' => ['The notes field must be a string.']]));
    expect(Blade::render('<x-portal-notices :validation-errors="$errors" />', ['errors' => $errors]))
        ->toContain('Please check the following:', 'The notes field must be a string.');
    $this->get(route('admin.form-submissions.show', $this->lead))->assertSuccessful();
    expect($this->lead->fresh()->notes)->toBeNull();
});
