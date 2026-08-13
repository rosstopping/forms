<?php

use App\Ai\Agents\ProspectOutreachWriter;
use App\Jobs\AnalyzeProspect;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\User;
use App\Services\ProspectWebsiteAnalyzer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

it('instructs generated outreach to use the approved wording and omit audit findings', function () {
    $instructions = (string) app(ProspectOutreachWriter::class)->instructions();

    expect($instructions)
        ->toContain('this exact wording')
        ->toContain('I ran your website through it and recorded a quick video showing what I found.')
        ->toContain('do not add website audit findings');
});

it('adds a prospect and automatically queues website research', function () {
    Queue::fake();
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($user)->post(route('admin.prospects.store'), [
        'business_name' => 'Acme Plumbing',
        'contact_name' => 'Alex',
        'email' => 'alex@example.com',
        'website_url' => 'https://example.com/',
        'showcase_video_url' => 'https://video.example.com/acme-plumbing',
    ])->assertRedirect();

    $prospect = Prospect::query()->sole();
    expect($prospect->user_id)->toBe($user->id)
        ->and($prospect->website_url)->toBe('https://example.com')
        ->and($prospect->showcase_video_url)->toBe('https://video.example.com/acme-plumbing')
        ->and($prospect->activities()->where('type', 'created')->exists())->toBeTrue();
    Queue::assertPushed(AnalyzeProspect::class, fn (AnalyzeProspect $job): bool => $job->prospect->is($prospect));
});

it('adds a website opportunity without queueing website research', function () {
    Queue::fake();
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($user)->post(route('admin.prospects.store'), [
        'business_name' => 'Bristol Builders',
        'email' => 'hello@example.com',
        'website_url' => '',
    ])->assertRedirect();

    $prospect = Prospect::query()->sole();
    expect($prospect->website_url)->toBeNull()
        ->and($prospect->analysis_status)->toBe('skipped')
        ->and($prospect->status)->toBe('drafted')
        ->and($prospect->outreach_subject)->toBe('Quick one for Bristol Builders')
        ->and($prospect->outreach_body)->toContain('quick video below')
        ->and($prospect->outreach_body)->not->toContain('health checks');
    Queue::assertNotPushed(AnalyzeProspect::class);
});

it('restricts outreach to Sitewell administrators', function () {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create();

    $this->actingAs($user)->get(route('admin.prospects.index'))->assertForbidden();
    $this->get(route('admin.prospects.show', $prospect))->assertForbidden();
    $this->post(route('admin.prospects.analyse', $prospect))->assertForbidden();
    $this->post(route('admin.prospects.approve', $prospect))->assertForbidden();
    $this->post(route('admin.prospects.test-email', $prospect))->assertForbidden();
    $this->post(route('admin.prospects.send', $prospect))->assertForbidden();
    $this->get(route('admin.dashboard'))->assertDontSee('Outreach');
});

it('allows an administrator to delete a prospect after confirmation in the interface', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($user, 'owner')->create();

    $this->actingAs($user)->get(route('admin.prospects.show', $prospect))
        ->assertSuccessful()
        ->assertSee('Delete this prospect?')
        ->assertSee('data-confirm-action-form', false)
        ->assertSee('data-confirm-action-dialog', false)
        ->assertSee('data-confirm-action-submit', false);

    $this->delete(route('admin.prospects.destroy', $prospect))
        ->assertRedirectToRoute('admin.prospects.index')
        ->assertSessionHas('status', 'Prospect deleted.');

    $this->assertModelMissing($prospect);
});

it('shows discovered public contact details with their source', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($user, 'owner')->create([
        'analysis_status' => 'completed',
        'contact_details' => [
            'emails' => [['value' => 'hello@example.com', 'source_url' => 'https://example.com/contact']],
            'phones' => [['value' => '+441171234567', 'source_url' => 'https://example.com/contact']],
            'contact_page_url' => 'https://example.com/contact',
            'contact_form_url' => 'https://example.com/contact',
        ],
    ]);

    $this->actingAs($user)->get(route('admin.prospects.show', $prospect))
        ->assertSuccessful()
        ->assertSee('Public contact details')
        ->assertSee('hello@example.com')
        ->assertSee('+441171234567')
        ->assertSee('View source');
});

it('fills an empty prospect email from published website contact details', function () {
    $prospect = Prospect::factory()->create(['email' => null]);
    $analyzer = Mockery::mock(ProspectWebsiteAnalyzer::class);
    $analyzer->shouldReceive('analyze')->once()->with($prospect->website_url)->andReturn([
        'score' => 0,
        'findings' => [],
        'contacts' => [
            'emails' => [['value' => 'hello@example.com', 'source_url' => 'https://example.com/contact']],
            'phones' => [],
            'contact_page_url' => 'https://example.com/contact',
            'contact_form_url' => null,
        ],
    ]);

    (new AnalyzeProspect($prospect))->handle($analyzer);

    expect($prospect->refresh()->email)->toBe('hello@example.com')
        ->and(data_get($prospect->contact_details, 'emails.0.source_url'))->toBe('https://example.com/contact')
        ->and($prospect->approved_at)->toBeNull()
        ->and($prospect->sent_at)->toBeNull();
});

it('prepares the approved outreach wording with the prospect company name', function () {
    $prospect = Prospect::factory()->create([
        'business_name' => 'New Bould Roofing',
        'website_url' => 'https://newbould.example',
    ]);
    $analyzer = Mockery::mock(ProspectWebsiteAnalyzer::class);
    $analyzer->shouldReceive('analyze')->once()->andReturn([
        'score' => 0,
        'findings' => [],
        'contacts' => ['emails' => [], 'phones' => [], 'addresses' => [], 'contact_page_url' => null, 'contact_form_url' => null],
    ]);

    (new AnalyzeProspect($prospect))->handle($analyzer);

    expect($prospect->refresh()->outreach_body)->toBe("Hi there,\n\nI came across New Bould Roofing on Google and thought Sitewell might be useful for you.\n\nI ran your website through it and recorded a quick video showing what I found.\n\nNo sales pitch — just thought it might be worth a look.\n\nCheers,\nRoss");
});

it('requires approval before sending outreach and schedules a follow-up', function () {
    Mail::fake();
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($user, 'owner')->create([
        'status' => 'drafted',
        'outreach_subject' => 'A website opportunity',
        'outreach_body' => 'Hi Alex, I noticed a missing page description.',
        'showcase_video_url' => 'https://video.example.com/acme-plumbing',
    ]);

    $this->actingAs($user)->post(route('admin.prospects.send', $prospect))->assertUnprocessable();
    $this->post(route('admin.prospects.approve', $prospect))->assertRedirect();
    $this->post(route('admin.prospects.send', $prospect))->assertRedirect();

    $prospect->refresh();
    expect($prospect->status)->toBe('contacted')
        ->and($prospect->approved_at)->not->toBeNull()
        ->and($prospect->sent_at)->not->toBeNull()
        ->and($prospect->next_follow_up_at)->not->toBeNull()
        ->and($prospect->activities()->where('type', 'sent')->exists())->toBeTrue();
    Mail::assertSent(ProspectOutreach::class, fn (ProspectOutreach $mail): bool => $mail->hasTo($prospect->email));
});

it('sends the exact saved draft as a test to the administrator without contacting the prospect', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'email' => 'admin@sitewell.example']);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'email' => 'prospect@example.com',
        'status' => 'drafted',
        'outreach_subject' => 'Quick one for Acme Plumbing',
        'outreach_body' => "Hi Alex,\n\nI've included a quick video below.",
        'showcase_video_url' => 'https://video.example.com/acme-plumbing',
        'approved_at' => null,
        'sent_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.prospects.show', $prospect))
        ->assertSuccessful()
        ->assertSee('Send test to admin@sitewell.example');

    $this->post(route('admin.prospects.test-email', $prospect))
        ->assertRedirect()
        ->assertSessionHas('status', 'Test email sent to admin@sitewell.example.');

    Mail::assertSent(ProspectOutreach::class, function (ProspectOutreach $mail) use ($admin, $prospect): bool {
        $mail->assertHasSubject($prospect->outreach_subject)
            ->assertSeeInHtml($prospect->outreach_body)
            ->assertSeeInHtml('https://video.example.com/acme-plumbing')
            ->assertSeeInHtml('Your website video')
            ->assertSeeInHtml('https://cal.com/ross');

        return $mail->hasTo($admin->email) && ! $mail->hasTo($prospect->email);
    });

    expect($prospect->fresh()->sent_at)->toBeNull()
        ->and($prospect->fresh()->approved_at)->toBeNull()
        ->and($prospect->activities()->where('type', 'test_email_sent')->exists())->toBeTrue();
});

it('stores a prospect-specific showcase video and resets approval when it changes', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'status' => 'approved',
        'outreach_subject' => 'Quick one',
        'outreach_body' => 'Hi there.',
        'showcase_video_url' => 'https://video.example.com/original',
        'approved_at' => now(),
        'approved_by' => $admin->id,
    ]);

    $this->actingAs($admin)->put(route('admin.prospects.update', $prospect), [
        'business_name' => $prospect->business_name,
        'contact_name' => $prospect->contact_name,
        'email' => $prospect->email,
        'website_url' => $prospect->website_url,
        'status' => $prospect->status,
        'outreach_subject' => $prospect->outreach_subject,
        'outreach_body' => $prospect->outreach_body,
        'showcase_video_url' => 'https://video.example.com/personalised',
    ])->assertRedirect();

    expect($prospect->refresh()->showcase_video_url)->toBe('https://video.example.com/personalised')
        ->and($prospect->approved_at)->toBeNull()
        ->and($prospect->approved_by)->toBeNull()
        ->and($prospect->status)->toBe('drafted');
});

it('rejects an invalid prospect showcase video URL', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->post(route('admin.prospects.store'), [
        'business_name' => 'Acme Plumbing',
        'showcase_video_url' => 'not-a-url',
    ])->assertInvalid('showcase_video_url');
});

it('will not send to a suppressed prospect', function () {
    Mail::fake();
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($user, 'owner')->create([
        'status' => 'approved',
        'outreach_subject' => 'Hello',
        'outreach_body' => 'A reviewed message.',
        'approved_at' => now(),
        'suppressed_at' => now(),
    ]);

    $this->actingAs($user)->post(route('admin.prospects.send', $prospect))->assertUnprocessable();
    Mail::assertNothingSent();
});

it('will not send test or live outreach without a prospect showcase video', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'outreach_subject' => 'Quick one',
        'outreach_body' => 'Hi there.',
        'approved_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.prospects.test-email', $prospect))
        ->assertUnprocessable();
    $this->post(route('admin.prospects.send', $prospect))->assertUnprocessable();

    Mail::assertNothingSent();
});

it('shares a time-limited website review with the prospect', function () {
    $prospect = Prospect::factory()->create([
        'business_name' => 'Acme Plumbing',
        'website_url' => 'https://example.com',
        'opportunity_score' => 24,
        'analysed_at' => now(),
        'findings' => [[
            'key' => 'meta_description',
            'title' => 'Meta description',
            'severity' => 'warning',
            'message' => 'The homepage has no meta description.',
        ]],
    ]);

    $this->get(URL::temporarySignedRoute('prospect-reports.show', now()->addDays(30), $prospect))
        ->assertSuccessful()
        ->assertSee('Website review')
        ->assertSee('Acme Plumbing')
        ->assertSee('The homepage has no meta description.');
    $this->get(route('prospect-reports.show', $prospect))->assertForbidden();
});

it('renders a casual outreach email with the showcase video and no audit details', function () {
    $prospect = Prospect::factory()->create([
        'business_name' => 'Acme Plumbing',
        'outreach_subject' => 'Quick one for Acme Plumbing',
        'outreach_body' => "Hi Alex,\n\nI've included a quick video below so you can see what Sitewell does.",
        'showcase_video_url' => 'https://video.example.com/acme-plumbing',
    ]);

    (new ProspectOutreach($prospect))
        ->assertHasSubject('Quick one for Acme Plumbing')
        ->assertSeeInHtml('Your website video')
        ->assertSeeInHtml('Watch your video')
        ->assertSeeInHtml('https://video.example.com/acme-plumbing')
        ->assertSeeInHtml('Book a call with Ross')
        ->assertSeeInHtml('https://cal.com/ross')
        ->assertSeeInHtml('quick video below')
        ->assertDontSeeInHtml('View your website review')
        ->assertDontSeeInHtml('What is Sitewell?')
        ->assertDontSeeInHtml('Website health checks')
        ->assertDontSeeInHtml('signature=');
});

it('includes the showcase video when offering a prospect a new website', function () {
    $prospect = Prospect::factory()->create([
        'website_url' => null,
        'outreach_subject' => 'Quick one for Acme Plumbing',
        'outreach_body' => 'Hi there, I could not see a website linked from the business listing.',
        'showcase_video_url' => 'https://video.example.com/new-website',
    ]);

    (new ProspectOutreach($prospect))
        ->assertHasSubject('Quick one for Acme Plumbing')
        ->assertSeeInHtml('Your website video')
        ->assertSeeInHtml('Watch your video')
        ->assertSeeInHtml('https://video.example.com/new-website')
        ->assertSeeInHtml('Book a call with Ross')
        ->assertSeeInHtml('https://cal.com/ross')
        ->assertDontSeeInHtml('View your website review')
        ->assertDontSeeInHtml('signature=');
});
