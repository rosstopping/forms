<?php

use App\Models\Form;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;

it('prioritises website audit health on the dashboard', function (): void {
    $user = User::factory()->create();
    $healthyWebsite = Website::factory()->for($user, 'owner')->create(['name' => 'Healthy website']);
    $warningWebsite = Website::factory()->for($user, 'owner')->create(['name' => 'Website needing work']);

    WebsiteHealthReport::factory()->for($healthyWebsite)->create([
        'overall_status' => 'healthy',
        'warning_checks' => 0,
        'failed_checks' => 0,
    ]);
    WebsiteHealthReport::factory()->for($warningWebsite)->create([
        'overall_status' => 'needs_attention',
        'warning_checks' => 3,
        'failed_checks' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Site health overview')
        ->assertSee('Audit workspace')
        ->assertSee('data-mobile-nav-toggle', false)
        ->assertSee('data-mobile-nav', false)
        ->assertSee('Healthy website')
        ->assertSee('Website needing work')
        ->assertSee('Needs attention')
        ->assertSee('Recent audit activity')
        ->assertDontSee('href="'.route('admin.forms.index').'"', false)
        ->assertDontSee('href="'.route('admin.form-submissions.index').'"', false);
});

it('keeps forms and submissions inside the website workspace', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create(['name' => 'Client website']);
    Form::factory()->for($website)->create(['name' => 'Contact form']);

    $this->actingAs($user)
        ->get(route('admin.websites.show', $website))
        ->assertOk()
        ->assertSee('Health reports')
        ->assertSee('data-tab="content"', false)
        ->assertSee('Forms & submissions', false)
        ->assertSee('role="tablist"', false)
        ->assertSee('data-tab-panel="health"', false)
        ->assertSee('data-tab-panel="forms" hidden', false)
        ->assertDontSee('href="#health"', false)
        ->assertSee('Contact form')
        ->assertSee('Recent submissions');
});

it('shows the latest audit status on the websites index', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create();
    WebsiteHealthReport::factory()->for($website)->create([
        'overall_status' => 'critical',
        'failed_checks' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('admin.websites.index'))
        ->assertOk()
        ->assertSee('Latest audit')
        ->assertSee('Critical')
        ->assertSee('2 failed');
});
