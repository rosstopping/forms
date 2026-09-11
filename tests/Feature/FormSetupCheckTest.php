<?php

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteDomain;
use App\Services\FormSetupChecker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Mail::fake();
    Queue::fake();
    Notification::fake();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com', 'mail.from.address' => 'forms@example.com']);
    $this->owner = User::factory()->create();
    $this->website = Website::factory()->for($this->owner, 'owner')->create(['turnstile_enabled' => false]);
    $this->website->domains()->create(['domain' => 'example.com', 'is_primary' => true, 'ownership_status' => WebsiteDomain::OWNERSHIP_VERIFIED]);
    $this->form = Form::factory()->for($this->website)->create(['name' => 'Contact', 'slug' => 'contact', 'email_recipients_override' => ['team@example.com']]);
});

it('saves and displays a configuration check without submissions or outbound work', function (): void {
    $this->actingAs($this->owner)->get(route('admin.forms.show', $this->form))->assertSuccessful()->assertSee('Check form setup')->assertSee('Never.');
    $this->post(route('admin.forms.setup-check', $this->form))->assertRedirect(route('admin.forms.show', $this->form));
    $this->form->refresh();
    expect($this->form->setup_checked_at)->not->toBeNull()
        ->and(collect($this->form->setup_check_results)->pluck('status')->unique()->all())->toBe(['passed'])
        ->and($this->form->last_submission_at)->toBeNull()
        ->and(FormSubmission::count())->toBe(0)
        ->and(Form::count())->toBe(1)->and(Website::count())->toBe(1);
    $this->get(route('admin.forms.show', $this->form))->assertSuccessful()->assertSee('Configuration checks passed')->assertSee('Last checked:')->assertSee('inbox delivery is untested');
    Http::assertNothingSent();
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
    Notification::assertNothingSent();
});

it('reports disabled forms, unresolved domains, invalid recipients and incomplete delivery configuration', function (): void {
    $this->website->update(['is_active' => false, 'turnstile_enabled' => true, 'turnstile_site_key' => null, 'turnstile_secret_key' => null]);
    $this->website->domains()->update(['ownership_status' => WebsiteDomain::OWNERSHIP_PENDING]);
    $this->form->update(['is_active' => false, 'slug' => 'wrong-reference', 'email_recipients_override' => ['invalid'], 'webhook_enabled_override' => true, 'webhook_url_override' => 'javascript:alert(1)']);
    config(['mail.default' => 'log']);
    $results = collect(app(FormSetupChecker::class)->check($this->form->fresh()));
    expect($results->where('status', 'needs_attention')->pluck('key')->all())->toBe([
        'website_active', 'form_active', 'form_reference', 'origins', 'turnstile', 'recipients', 'mail', 'webhook',
    ]);
});

it('treats optional disabled delivery as valid and excludes viewer notification recipients', function (): void {
    $viewer = User::factory()->create();
    $this->website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->form->update(['email_recipients_override' => [$viewer->email]]);
    $checks = collect(app(FormSetupChecker::class)->check($this->form->fresh()))->keyBy('key');
    expect($checks['recipients']['status'])->toBe('needs_attention');
    $this->form->update(['email_enabled_override' => false, 'webhook_enabled_override' => false]);
    $checks = collect(app(FormSetupChecker::class)->check($this->form->fresh()))->keyBy('key');
    expect($checks['recipients']['status'])->toBe('passed')->and($checks->has('mail'))->toBeFalse()->and($checks['webhook']['status'])->toBe('passed');
});

it('does not disclose secrets and replaces the previous saved result when rerun', function (): void {
    $secret = 'secret-not-for-check-results';
    $this->website->update(['turnstile_enabled' => true, 'turnstile_site_key' => 'public-key', 'turnstile_secret_key' => $secret]);
    $this->form->update(['webhook_enabled_override' => true, 'webhook_url_override' => 'https://hooks.example.com/'.$secret, 'webhook_secret_override' => $secret]);
    $this->actingAs($this->owner)->post(route('admin.forms.setup-check', $this->form))->assertRedirect();
    expect(json_encode($this->form->fresh()->setup_check_results))->not->toContain($secret);
    $this->travel(1)->minutes();
    $this->form->update(['is_active' => false]);
    $this->post(route('admin.forms.setup-check', $this->form))->assertRedirect();
    expect(collect($this->form->fresh()->setup_check_results)->firstWhere('key', 'form_active')['status'])->toBe('needs_attention');
});

it('only lets website managers and admins run checks', function (): void {
    $viewer = User::factory()->create();
    $manager = User::factory()->create();
    $outsider = User::factory()->create();
    $this->website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->website->members()->attach($manager, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $this->get(route('admin.forms.show', $this->form))->assertRedirect(route('login'));
    $this->actingAs($viewer)->get(route('admin.forms.show', $this->form))->assertSuccessful()->assertDontSee('Check form setup');
    $this->actingAs($viewer)->post(route('admin.forms.setup-check', $this->form))->assertForbidden();
    $this->actingAs($outsider)->post(route('admin.forms.setup-check', $this->form))->assertForbidden();
    expect($this->form->fresh()->setup_checked_at)->toBeNull();
    $this->actingAs($manager)->post(route('admin.forms.setup-check', $this->form))->assertRedirect();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)->post(route('admin.forms.setup-check', $this->form))->assertRedirect();
});
