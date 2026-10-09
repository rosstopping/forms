<?php

use App\Models\User;
use App\Models\Website;
use App\Support\MembershipPlan;

it('uses a customers only website as their current website', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create(['name' => 'Acme Studio']);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Acme Studio')
        ->assertDontSee(route('admin.websites.section', [$website, 'health']))
        ->assertDontSee(route('admin.websites.section', [$website, 'content']))
        ->assertDontSee('>Websites</a>', false);

    expect($user->fresh()->current_website_id)->toBe($website->id);
});

it('remembers the website visited through a website section route', function (): void {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $firstWebsite = Website::factory()->for($user, 'owner')->create(['name' => 'First website']);
    $secondWebsite = Website::factory()->for($user, 'owner')->create(['name' => 'Second website']);
    $user->update(['current_website_id' => $firstWebsite->id]);

    $this->actingAs($user)
        ->get(route('admin.websites.section', [$secondWebsite, 'content']))
        ->assertOk()
        ->assertSee('data-default-tab="content"', false)
        ->assertSee('data-website-switcher', false);

    expect($user->fresh()->current_website_id)->toBe($secondWebsite->id);
});

it('switches to another accessible website and preserves the section', function (): void {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $firstWebsite = Website::factory()->for($user, 'owner')->create();
    $secondWebsite = Website::factory()->for($user, 'owner')->create();
    $user->update(['current_website_id' => $firstWebsite->id]);

    $this->actingAs($user)
        ->post(route('admin.current-website.update'), [
            'website_id' => $secondWebsite->id,
            'section' => 'settings',
        ])
        ->assertRedirect(route('admin.websites.section', [$secondWebsite, 'settings']));

    expect($user->fresh()->current_website_id)->toBe($secondWebsite->id);
});

it('switches websites from Google Ads and opens the selected website Ads page', function (): void {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $firstWebsite = Website::factory()->for($user, 'owner')->create();
    $secondWebsite = Website::factory()->for($user, 'owner')->create();

    $this->actingAs($user)
        ->get(route('admin.google-ads.index', $firstWebsite))
        ->assertOk()
        ->assertSee('name="section" value="google-ads"', false);

    $this->post(route('admin.current-website.update'), [
        'website_id' => $secondWebsite->id,
        'section' => 'google-ads',
    ])->assertRedirect(route('admin.google-ads.index', $secondWebsite));

    expect($user->fresh()->current_website_id)->toBe($secondWebsite->id);
});

it('switches customers back to Overview instead of Google Ads', function (): void {
    $user = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::GROWTH, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->members()->attach($user, ['role' => Website::MEMBER_ROLE_MANAGER]);

    $this->actingAs($user)->post(route('admin.current-website.update'), [
        'website_id' => $website->id,
        'section' => 'google-ads',
    ])->assertRedirect(route('admin.dashboard'));

    expect($user->fresh()->current_website_id)->toBe($website->id);
});

it('does not allow users to switch to inaccessible websites', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create();
    $inaccessibleWebsite = Website::factory()->create();
    $user->update(['current_website_id' => $website->id]);

    $this->actingAs($user)
        ->post(route('admin.current-website.update'), [
            'website_id' => $inaccessibleWebsite->id,
            'section' => 'health',
        ])
        ->assertNotFound();

    expect($user->fresh()->current_website_id)->toBe($website->id);
});

it('keeps the websites directory available to administrators', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    Website::factory()->create(['name' => 'Managed customer website']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('>Websites</a>', false)
        ->assertSee('Managed customer website');
});
