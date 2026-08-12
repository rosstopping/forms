<?php

use App\Jobs\AnalyzeProspect;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

it('adds a prospect and automatically queues website research', function () {
    Queue::fake();
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($user)->post(route('admin.prospects.store'), [
        'business_name' => 'Acme Plumbing',
        'contact_name' => 'Alex',
        'email' => 'alex@example.com',
        'website_url' => 'https://example.com/',
    ])->assertRedirect();

    $prospect = Prospect::query()->sole();
    expect($prospect->user_id)->toBe($user->id)
        ->and($prospect->website_url)->toBe('https://example.com')
        ->and($prospect->activities()->where('type', 'created')->exists())->toBeTrue();
    Queue::assertPushed(AnalyzeProspect::class, fn (AnalyzeProspect $job): bool => $job->prospect->is($prospect));
});

it('restricts outreach to Sitewell administrators', function () {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create();

    $this->actingAs($user)->get(route('admin.prospects.index'))->assertForbidden();
    $this->get(route('admin.prospects.show', $prospect))->assertForbidden();
    $this->post(route('admin.prospects.analyse', $prospect))->assertForbidden();
    $this->post(route('admin.prospects.approve', $prospect))->assertForbidden();
    $this->post(route('admin.prospects.send', $prospect))->assertForbidden();
    $this->get(route('admin.dashboard'))->assertDontSee('Outreach');
});

it('requires approval before sending outreach and schedules a follow-up', function () {
    Mail::fake();
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($user, 'owner')->create([
        'status' => 'drafted',
        'outreach_subject' => 'A website opportunity',
        'outreach_body' => 'Hi Alex, I noticed a missing page description.',
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

it('makes the outreach email warmer and includes the private report link', function () {
    $prospect = Prospect::factory()->create([
        'business_name' => 'Acme Plumbing',
        'outreach_subject' => 'A quick website review for Acme Plumbing',
        'outreach_body' => "Hi Alex,\n\nI had a look at your website and included a short review.",
    ]);

    (new ProspectOutreach($prospect))
        ->assertHasSubject('A quick website review for Acme Plumbing')
        ->assertSeeInHtml('View your website review')
        ->assertSeeInHtml('This private link expires in 30 days')
        ->assertSeeInHtml('What is Sitewell?')
        ->assertSeeInHtml('Forms, automatic acknowledgements, and a simple lead CRM')
        ->assertSeeInHtml('Google Business Profile posts and review replies')
        ->assertSeeInHtml('signature=')
        ->assertSeeInHtml('I had a look at your website');
});
