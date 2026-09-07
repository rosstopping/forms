<?php

use App\Models\User;
use App\Models\Website;

it('uses a customers only website as their current website', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create(['name' => 'Acme Studio']);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Acme Studio')
        ->assertSee(route('admin.websites.section', [$website, 'health']))
        ->assertSee(route('admin.websites.section', [$website, 'content']))
        ->assertDontSee('>Websites</a>', false);

    expect($user->fresh()->current_website_id)->toBe($website->id);
});

it('remembers the website visited through a website section route', function (): void {
    $user = User::factory()->create();
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
    $user = User::factory()->create();
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
