<?php

use App\Jobs\AnalyzeProspect;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('adds a prospect and automatically queues website research', function () {
    Queue::fake();
    $user = User::factory()->create();

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

it('scopes outreach prospects to their owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    Prospect::factory()->for($owner, 'owner')->create(['business_name' => 'Visible Company']);
    $hidden = Prospect::factory()->for($otherUser, 'owner')->create(['business_name' => 'Private Company']);

    $this->actingAs($owner)->get(route('admin.prospects.index'))
        ->assertOk()
        ->assertSee('Visible Company')
        ->assertDontSee('Private Company');
    $this->get(route('admin.prospects.show', $hidden))->assertForbidden();
});

it('requires approval before sending outreach and schedules a follow-up', function () {
    Mail::fake();
    $user = User::factory()->create();
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
    $user = User::factory()->create();
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
