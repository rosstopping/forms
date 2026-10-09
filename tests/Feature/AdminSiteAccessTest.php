<?php

use App\Models\ContentRequest;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use App\Models\SeoWin;
use App\Models\User;
use App\Models\Website;
use App\Services\WebsiteMailRecipients;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    Mail::fake();
    $this->globalAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->staff = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_site_access' => 'assigned']);
    $this->site = Website::factory()->create(['name' => 'Assigned client']);
    $this->foreign = Website::factory()->create(['name' => 'Other private client']);
    $this->staff->assignedWebsites()->attach($this->site);
    $this->staff->update(['current_website_id' => $this->site->id]);
});

test('staff assignments override legacy ownership and membership without changing customer access', function (): void {
    $this->foreign->update(['user_id' => $this->staff->id]);
    $this->foreign->members()->attach($this->staff, ['role' => Website::MEMBER_ROLE_MANAGER]);
    expect(Website::accessibleTo($this->staff)->pluck('id')->all())->toBe([$this->site->id])
        ->and(Website::manageableBy($this->staff)->pluck('id')->all())->toBe([$this->site->id])
        ->and($this->foreign->isAccessibleBy($this->staff))->toBeFalse()->and($this->foreign->isManageableBy($this->staff))->toBeFalse();
    $customer = User::factory()->create();
    $this->foreign->members()->attach($customer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    expect($this->foreign->isAccessibleBy($customer))->toBeTrue()->and($this->foreign->isAccessibleBy($this->globalAdmin))->toBeTrue();
});

test('assigned staff dashboard switcher directory and forms are scoped', function (): void {
    Form::factory()->for($this->foreign)->create(['name' => 'Private foreign form']);
    $this->actingAs($this->staff)->get(route('admin.overview'))->assertSuccessful()->assertSee('Assigned client')->assertDontSee('Other private client');
    $this->get(route('admin.websites.index'))->assertSuccessful()->assertDontSee('Other private client');
    $this->get(route('admin.forms.index'))->assertSuccessful()->assertDontSee('Private foreign form');
    $this->post(route('admin.current-website.update'), ['website_id' => $this->foreign->id])->assertNotFound();
    $this->get(route('admin.websites.section', [$this->site, 'settings']))->assertSuccessful();
    $this->get(route('admin.websites.section', [$this->foreign, 'settings']))->assertForbidden();
});

test('assigned staff cannot reach unassigned operational endpoints or global administration', function (string $method, string $route, array $parameters): void {
    $parameters = array_map(fn ($value) => $value === 'site' ? $this->foreign : ($value === 'user' ? $this->staff : $value), $parameters);
    $this->actingAs($this->staff)->call($method, route($route, $parameters), ['role' => 'admin', 'admin_site_access' => 'all'])->assertForbidden();
    Http::assertNothingSent();
    Queue::assertNothingPushed();
})->with([
    ['GET', 'admin.users.index', []], ['GET', 'admin.users.edit', ['user']], ['PUT', 'admin.users.update', ['user']],
    ['POST', 'admin.users.store', []], ['GET', 'admin.website-builder.create', []],
    ['GET', 'admin.websites.section', ['site', 'content']], ['PUT', 'admin.websites.update', ['site']],
    ['POST', 'admin.website-health-reports.store', ['site']], ['POST', 'admin.content-requests.store', ['site']],
    ['DELETE', 'admin.search-console.destroy', ['site']],
]);

test('unassigned nested records are rejected even when the outer website is assigned', function (): void {
    $foreignRequest = ContentRequest::factory()->for($this->foreign)->create();
    $lead = FormSubmission::factory()->for($this->foreign)->create();
    $win = SeoWin::factory()->for($this->foreign)->create();
    $this->actingAs($this->staff)->delete(route('admin.content-requests.destroy', [$this->site, $foreignRequest]))->assertForbidden();
    $this->put(route('admin.form-submissions.update', $lead), ['status' => 'contacted'])->assertForbidden();
    $this->patch(route('admin.seo-wins.update', $win), ['action' => 'approve', 'client_draft' => 'Private'])->assertForbidden();
});

test('all-site admins assign and revoke site access without touching customer memberships', function (): void {
    $this->actingAs($this->globalAdmin)->get(route('admin.users.edit', $this->staff))->assertSuccessful()->assertSee('Assigned websites only')->assertSee('All websites');
    $this->put(route('admin.users.update', $this->staff), ['name' => $this->staff->name, 'email' => $this->staff->email,
        'role' => 'admin', 'admin_site_access' => 'assigned', 'assigned_website_ids' => [$this->foreign->id]])->assertRedirect();
    expect($this->staff->fresh()->assignedWebsites->modelKeys())->toBe([$this->foreign->id])->and($this->staff->fresh()->current_website_id)->toBeNull();
    $this->actingAs($this->staff->fresh())->get(route('admin.websites.section', [$this->site, 'settings']))->assertForbidden();
    $this->get(route('admin.websites.section', [$this->foreign, 'settings']))->assertSuccessful();
});

test('empty assignments deny every website and final all-site admin cannot lose access', function (): void {
    $this->staff->assignedWebsites()->detach();
    expect(Website::accessibleTo($this->staff)->count())->toBe(0);
    $this->actingAs($this->globalAdmin)->put(route('admin.users.update', $this->globalAdmin), ['name' => $this->globalAdmin->name,
        'email' => $this->globalAdmin->email, 'role' => 'admin', 'admin_site_access' => 'assigned'])->assertSessionHasErrors('admin_site_access');
    expect($this->globalAdmin->fresh()->hasAllWebsiteAccess())->toBeTrue();
});

test('assigned admins cannot impersonate customers or receive reports for unassigned sites', function (): void {
    $customer = User::factory()->create();
    $this->foreign->members()->attach($this->staff, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $this->actingAs($this->staff)->post(route('admin.users.impersonate.store', $customer))->assertForbidden();
    expect(app(WebsiteMailRecipients::class)->forReports($this->foreign))->not->toContain($this->staff->email)
        ->and(app(WebsiteMailRecipients::class)->forReports($this->site))->toContain($this->staff->email, $this->globalAdmin->email);
});

test('customer search overview is read only property scoped and excludes staff data tables', function (): void {
    $customer = $this->site->owner;
    $customer->update(['current_website_id' => $this->site->id]);
    $connection = SearchConsoleConnection::factory()->for($this->site)->create();
    SearchConsoleMetric::factory()->create(['website_id' => $this->site->id, 'search_console_connection_id' => $connection->id,
        'property_url' => $connection->property_url, 'property_hash' => hash('sha256', $connection->property_url),
        'dimension_key' => 'site', 'month' => now()->subMonth()->startOfMonth(), 'clicks' => 120, 'impressions' => 1234]);
    $response = $this->actingAs($customer)->get(route('admin.search-overview', $this->site))->assertSuccessful()
        ->assertSee('Performance overview')->assertSee('Clicks and impressions')->assertSee('Average position')
        ->assertDontSee('Disconnect')->assertDontSee('Connect Google')->assertDontSee('View all data')->assertDontSee('Top query sample')->assertDontSee('Prepare improvement');
    expect($response->viewData('searchHistory'))->toHaveCount(1);
    $this->get(route('admin.websites.section', [$this->site, 'search']))->assertRedirect(route('admin.search-overview', $this->site));
    $this->get(route('admin.search-overview', $this->foreign))->assertForbidden();
    $this->delete(route('admin.search-console.destroy', $this->site))->assertForbidden();
    Http::assertNothingSent();
});

test('customer overview places weekly overview first and collapses timeline initially', function (): void {
    $customer = $this->site->owner;
    $customer->update(['current_website_id' => $this->site->id]);
    $response = $this->actingAs($customer)->get(route('admin.dashboard'))->assertSuccessful()->assertSeeInOrder(['Weekly Overview', 'SEO progress timeline']);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//section[@aria-labelledby="seo-progress-heading"]/details[not(@open)]')->length)->toBe(1);
});

test('assigned staff can manage their sites but cannot change service or billing ownership', function (): void {
    $this->actingAs($this->staff)->put(route('admin.websites.update', $this->site), ['name' => 'Managed client'])->assertRedirect();
    expect($this->site->fresh()->name)->toBe('Managed client');
    $this->put(route('admin.websites.service.update', $this->site), ['service_package' => 'complete', 'service_status' => 'active'])->assertForbidden();
    $this->put(route('admin.websites.update', $this->site), ['subscription_user_id' => $this->globalAdmin->id])->assertForbidden();
    $this->get(route('admin.websites.section', [$this->site, 'settings']))->assertSuccessful()->assertDontSee('id="website-service-title"', false)->assertDontSee('Delete website');
});

test('assigned staff OAuth callbacks recheck site access before requesting credentials', function (): void {
    $state = Crypt::encryptString(json_encode(['website_id' => $this->foreign->id, 'user_id' => $this->staff->id]));
    $this->actingAs($this->staff)->get(route('admin.search-console.callback', ['state' => $state, 'code' => 'unused']))->assertForbidden();
    Http::assertNothingSent();
});

test('assigned staff can edit assigned leads and only see relevant assignees', function (): void {
    $unrelated = User::factory()->create(['name' => 'Private unrelated customer']);
    $lead = FormSubmission::factory()->for($this->site)->create();
    $this->actingAs($this->staff)->get(route('admin.form-submissions.show', $lead))->assertSuccessful()->assertDontSee($unrelated->name);
    $this->put(route('admin.form-submissions.update', $lead), ['status' => 'contacted', 'notes' => 'Handled', 'assigned_to' => $this->staff->id])->assertRedirect();
    expect($lead->fresh()->notes)->toBe('Handled');
    $this->put(route('admin.form-submissions.update', $lead), ['status' => 'contacted', 'assigned_to' => $unrelated->id])->assertSessionHasErrors('assigned_to');
});

test('staff creation validates assignments and keeps customer memberships separate', function (): void {
    $payload = ['name' => 'Restricted teammate', 'email' => 'teammate@example.com', 'role' => 'admin',
        'password' => 'strong-password-123', 'password_confirmation' => 'strong-password-123',
        'admin_site_access' => 'assigned', 'assigned_website_ids' => [$this->site->id]];
    $this->actingAs($this->globalAdmin)->post(route('admin.users.store'), $payload)->assertRedirect();
    $user = User::where('email', 'teammate@example.com')->sole();
    expect($user->assignedWebsites->modelKeys())->toBe([$this->site->id])->and($user->sharedWebsites)->toHaveCount(0);
    $this->post(route('admin.users.store'), [...$payload, 'email' => 'invalid@example.com', 'assigned_website_ids' => [99999999]])->assertSessionHasErrors('assigned_website_ids.0');
    expect(User::where('email', 'invalid@example.com')->exists())->toBeFalse();
});

test('customer search graphs omit old-property metrics and query details', function (): void {
    $connection = SearchConsoleConnection::factory()->for($this->site)->create();
    SearchConsoleMetric::factory()->create(['website_id' => $this->site->id, 'search_console_connection_id' => $connection->id,
        'property_url' => 'sc-domain:previous.example', 'property_hash' => hash('sha256', 'sc-domain:previous.example'), 'dimension_key' => 'site', 'month' => now()->subMonth()->startOfMonth()]);
    $this->site->owner->update(['current_website_id' => $this->site->id]);
    $response = $this->actingAs($this->site->owner)->get(route('admin.search-overview', $this->site))->assertSuccessful();
    expect($response->viewData('searchHistory'))->toHaveCount(0);
    $this->get(route('admin.search-console.performance', $this->site))->assertForbidden();
});
