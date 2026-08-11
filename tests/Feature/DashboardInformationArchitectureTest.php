<?php

use App\Models\Form;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteRepository;

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
        ->assertSee('href="'.route('admin.form-submissions.index').'"', false);
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
        ->assertSee('data-tab-panel="content"', false)
        ->assertSee('Connect GitHub')
        ->assertDontSee('Manual content requests')
        ->assertSee('>Forms</button>', false)
        ->assertSee('role="tablist"', false)
        ->assertSee('data-tab-panel="health"', false)
        ->assertSee('data-tab-panel="forms" hidden', false)
        ->assertDontSee('href="#health"', false)
        ->assertSee('Connect a website form')
        ->assertSee(route('forms.submit'))
        ->assertSee('name="_form_name"', false)
        ->assertSee('name="_honeypot"', false)
        ->assertSee('data-copy-target="form-onboarding-example"', false)
        ->assertSee('Contact form')
        ->assertSee('Submissions')
        ->assertDontSee('Recent submissions');
});

it('shows content tools when the website has a GitHub repository', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create();
    WebsiteRepository::factory()->for($website)->create();

    $this->actingAs($user)
        ->get(route('admin.websites.show', $website))
        ->assertOk()
        ->assertSee('data-tab="content"', false)
        ->assertSee('data-tab-panel="content"', false)
        ->assertSee('Manual content requests');
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
