<?php

use App\Jobs\GenerateFreeSiteAudit;
use App\Mail\FreeSiteAuditResults;
use App\Models\Prospect;
use App\Models\User;
use App\Services\ProspectWebsiteAnalyzer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

it('captures a free audit request as a lead and queues the audit', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->from(route('marketing.free-site-audit'))
        ->post(route('marketing.free-site-audit.store'), [
            'name' => 'Alex Morgan',
            'email' => 'ALEX@EXAMPLE.COM',
            'business_name' => 'Northfield Studio',
            'website_url' => 'northfield.example',
            'consent' => '1',
            '_sitewell_check' => '',
        ])
        ->assertRedirect(route('marketing.free-site-audit'))
        ->assertSessionHas('status');

    $lead = Prospect::query()->sole();
    expect($lead->user_id)->toBe($admin->id)
        ->and($lead->contact_name)->toBe('Alex Morgan')
        ->and($lead->email)->toBe('alex@example.com')
        ->and($lead->website_url)->toBe('https://northfield.example')
        ->and($lead->notes)->toContain('free site audit')
        ->and($lead->activities()->where('type', 'free_audit_requested')->exists())->toBeTrue();

    Queue::assertPushed(GenerateFreeSiteAudit::class, fn (GenerateFreeSiteAudit $job): bool => $job->prospect->is($lead));
});

it('rejects invalid or automated free audit requests', function (): void {
    Queue::fake();
    User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->post(route('marketing.free-site-audit.store'), [
        'name' => '',
        'email' => 'invalid',
        'business_name' => '',
        'website_url' => 'not a website',
        '_sitewell_check' => 'bot content',
    ])->assertSessionHasErrors(['name', 'email', 'business_name', 'website_url', 'consent', '_sitewell_check']);

    expect(Prospect::query()->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

it('stores audit results and queues the customer results email', function (): void {
    Mail::fake();
    $prospect = Prospect::factory()->create([
        'email' => 'alex@example.com',
        'analysis_status' => 'pending',
    ]);
    $analyzer = Mockery::mock(ProspectWebsiteAnalyzer::class);
    $analyzer->shouldReceive('analyze')->once()->with($prospect->website_url)->andReturn([
        'score' => 35,
        'findings' => [['category' => 'Security', 'key' => 'https', 'title' => 'HTTPS enabled', 'severity' => 'warning', 'message' => 'HTTPS should be reviewed.']],
        'contacts' => ['emails' => [], 'phones' => [], 'contact_page_url' => null, 'contact_form_url' => null],
    ]);

    (new GenerateFreeSiteAudit($prospect))->handle($analyzer);

    $prospect->refresh();
    expect($prospect->analysis_status)->toBe('completed')
        ->and($prospect->status)->toBe('researched')
        ->and($prospect->opportunity_score)->toBe(35)
        ->and($prospect->activities()->where('type', 'free_audit_completed')->exists())->toBeTrue();

    Mail::assertQueued(FreeSiteAuditResults::class, fn (FreeSiteAuditResults $mail): bool => $mail->hasTo('alex@example.com'));
});

it('includes report and contact calls to action in the results email and landing page', function (): void {
    $prospect = Prospect::factory()->create([
        'business_name' => 'Northfield Studio',
        'contact_name' => 'Alex',
        'analysis_status' => 'completed',
        'analysed_at' => now(),
        'findings' => [['category' => 'Security', 'key' => 'https', 'title' => 'HTTPS enabled', 'severity' => 'passed', 'message' => 'HTTPS is enabled.']],
    ]);
    $mail = new FreeSiteAuditResults($prospect);

    $mail->assertSeeInHtml('View your audit results')
        ->assertSeeInHtml('Get in touch')
        ->assertSeeInHtml('Book a call with Ross');

    $reportUrl = URL::temporarySignedRoute('prospect-reports.show', now()->addHour(), ['prospect' => $prospect]);
    $this->get($reportUrl)
        ->assertSuccessful()
        ->assertSee('Your website audit')
        ->assertSee('Get in touch')
        ->assertSee('Book a call with Ross');
});
