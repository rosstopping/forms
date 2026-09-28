<?php

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectOutreachMessageType;
use App\Jobs\AnalyzeProspect;
use App\Jobs\SendScheduledProspectOutreach;
use App\Jobs\SendScheduledProspectPersonalisedVideo;
use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;
use App\Models\User;
use App\Services\InitialProspectOutreachGenerator;
use App\Services\ProspectWebsiteAnalyzer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('retains deleted prospects while cancelling their pending outreach and queued work', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $scheduledFor = now()->toImmutable()->addHour();
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'scheduled_send_at' => $scheduledFor,
        'next_follow_up_at' => $scheduledFor,
    ]);
    $sent = ProspectOutreachDelivery::factory()->for($prospect)->create();
    $pending = ProspectOutreachDelivery::factory()->for($prospect)->create([
        'message_type' => ProspectOutreachMessageType::PersonalisedVideo,
        'status' => 'scheduled',
        'sent_at' => null,
        'scheduled_at' => $scheduledFor,
    ]);

    $this->actingAs($admin)->delete(route('admin.prospects.destroy', $prospect))->assertRedirect();

    $this->assertSoftDeleted($prospect);
    $prospect->refresh();
    expect(Prospect::find($prospect->id))->toBeNull()
        ->and($prospect->scheduled_send_at)->toBeNull()
        ->and($prospect->next_follow_up_at)->toBeNull()
        ->and($prospect->outreachState->automation_status)->toBe(ProspectAutomationStatus::Stopped)
        ->and($prospect->outreachState->next_action_at)->toBeNull()
        ->and($prospect->outreachDeliveries()->count())->toBe(2)
        ->and($sent->fresh()->status)->toBe('sent')
        ->and($pending->fresh()->status)->toBe('cancelled')
        ->and($pending->fresh()->scheduled_at)->toBeNull()
        ->and($prospect->activities()->where('type', 'deleted')->exists())->toBeTrue();

    $this->mock(ProspectWebsiteAnalyzer::class)->shouldNotReceive('analyze');
    $this->mock(InitialProspectOutreachGenerator::class)->shouldNotReceive('generate');
    app()->call([new AnalyzeProspect($prospect), 'handle']);
    app()->call([new SendScheduledProspectOutreach($prospect->id, $scheduledFor), 'handle']);
    app()->call([new SendScheduledProspectPersonalisedVideo($pending->id, $scheduledFor), 'handle']);
    Mail::assertNothingSent();

    $this->get(route('admin.prospects.show', $prospect))->assertNotFound();
    $this->post(route('admin.prospects.send', $prospect))->assertNotFound();
    $this->post(route('admin.prospects.test-email', $prospect))->assertNotFound();
    $this->get(URL::signedRoute('prospect-outreach-opens.show', $sent))->assertNotFound();
    expect($sent->fresh()->open_count)->toBe(0);
});

it('searches deleted prospects across temperatures without exposing active records or bulk actions', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $deleted = Prospect::factory()->for($admin, 'owner')->create([
        'business_name' => 'Archived Plumbing', 'email' => 'archive@example.com', 'lead_temperature' => 'hot',
    ]);
    $deleted->delete();
    Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Active Plumbing', 'lead_temperature' => 'cold']);

    $this->actingAs($admin)->get(route('admin.prospects.index'))
        ->assertSuccessful()->assertSee('Active Plumbing')->assertDontSee('Archived Plumbing');

    foreach (['Plumbing', 'archive@example.com', 'no-match@example.com, archive@example.com'] as $search) {
        $this->get(route('admin.prospects.index', ['status' => 'deleted', 'search' => $search]))
            ->assertSuccessful()
            ->assertSee('Archived Plumbing')
            ->assertDontSee('Active Plumbing')
            ->assertDontSee('data-bulk-prospects-form', false)
            ->assertViewHas('prospects', fn ($prospects): bool => $prospects->pluck('id')->all() === [$deleted->id]);
    }

    $this->get(route('admin.prospects.index', ['status' => 'deleted', 'search' => 'unmatched']))
        ->assertSuccessful()->assertSee('No deleted prospects match your search.');
});
