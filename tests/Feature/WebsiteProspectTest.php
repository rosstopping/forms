<?php

use App\Jobs\AnalyzeProspect;
use App\Models\Prospect;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Queue;

it('allows an administrator to create an outreach prospect from a website', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->for($admin, 'owner')->create(['name' => 'Acme Roofing']);
    $website->domains()->create(['domain' => 'acme-roofing.example', 'is_primary' => true]);

    $this->actingAs($admin)->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('Create outreach prospect')
        ->assertDontSee('View outreach prospect');

    $this->post(route('admin.websites.prospect.store', $website))
        ->assertRedirect();

    $prospect = Prospect::query()->sole();
    expect($prospect->website_id)->toBe($website->id)
        ->and($prospect->business_name)->toBe('Acme Roofing')
        ->and($prospect->website_url)->toBe('https://acme-roofing.example')
        ->and($prospect->approved_at)->toBeNull()
        ->and($prospect->sent_at)->toBeNull();
    Queue::assertPushed(AnalyzeProspect::class, fn (AnalyzeProspect $job): bool => $job->prospect->is($prospect));

    $this->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('View outreach prospect')
        ->assertDontSee('Create outreach prospect');
});

it('links to an existing prospect with the same website domain without duplicating it', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->for($admin, 'owner')->create(['name' => 'Acme Roofing']);
    $website->domains()->create(['domain' => 'acme-roofing.example', 'is_primary' => true]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'website_id' => null,
        'website_url' => 'http://www.acme-roofing.example/',
    ]);

    $this->actingAs($admin)->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('View outreach prospect')
        ->assertSee(route('admin.prospects.show', $prospect), false);

    $this->post(route('admin.websites.prospect.store', $website))
        ->assertRedirect(route('admin.prospects.show', $prospect));

    expect(Prospect::query()->count())->toBe(1)
        ->and($prospect->refresh()->website_id)->toBe($website->id);
    Queue::assertNothingPushed();
});

it('does not show or allow the outreach action for non-administrators', function () {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'owner-site.example', 'is_primary' => true]);

    $this->actingAs($owner)->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertDontSee('Create outreach prospect')
        ->assertDontSee('View outreach prospect');

    $this->post(route('admin.websites.prospect.store', $website))->assertForbidden();
    expect(Prospect::query()->count())->toBe(0);
});
