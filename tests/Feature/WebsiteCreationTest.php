<?php

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteDomain;

it('allows an administrator to create a website without a form', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $owner = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.websites.create'))
        ->assertSuccessful()
        ->assertSee('Add website');

    $this->actingAs($admin)
        ->post(route('admin.websites.store'), [
            'name' => 'Acme Website',
            'domain' => 'https://www.Example.com/contact',
            'user_id' => $owner->id,
            'health_reports_enabled' => true,
        ])
        ->assertRedirect();

    $website = Website::query()->where('name', 'Acme Website')->firstOrFail();

    expect($website->user_id)->toBe($owner->id)
        ->and($website->health_reports_enabled)->toBeTrue()
        ->and($website->auto_discovered)->toBeFalse()
        ->and($website->forms()->count())->toBe(0)
        ->and($website->primaryDomain()?->domain)->toBe('www.example.com');
});

it('prevents non-administrators from creating websites', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.websites.create'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.websites.store'), [
            'name' => 'Forbidden Website',
            'domain' => 'forbidden.example.com',
            'health_reports_enabled' => false,
        ])
        ->assertForbidden();

    expect(Website::query()->where('name', 'Forbidden Website')->exists())->toBeFalse();
});

it('rejects an invalid or duplicate primary domain', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    WebsiteDomain::query()->create([
        'website_id' => Website::factory()->create()->id,
        'domain' => 'example.com',
        'is_primary' => true,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.websites.create'))
        ->post(route('admin.websites.store'), [
            'name' => 'Duplicate Website',
            'domain' => 'www.example.com',
            'health_reports_enabled' => false,
        ])
        ->assertRedirect(route('admin.websites.create'))
        ->assertSessionHasErrors('domain');

    expect(Website::query()->where('name', 'Duplicate Website')->exists())->toBeFalse();
});

it('allows an administrator to correct a website domain from settings', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create(['seo_history_backfilled_at' => now()]);
    $website->domains()->create(['domain' => 'wrong.example.com', 'is_primary' => true]);

    $this->actingAs($admin)
        ->get(route('admin.websites.show', [$website, 'tab' => 'settings']))
        ->assertSuccessful()
        ->assertSee('Website domain or URL')
        ->assertSee('wrong.example.com');

    $this->actingAs($admin)
        ->put(route('admin.websites.update', $website), [
            'name' => $website->name,
            'domain' => 'https://www.Correct-Example.com/services',
            'user_id' => $website->user_id,
        ])
        ->assertRedirect(route('admin.websites.show', ['website' => $website, 'tab' => 'settings']))
        ->assertSessionHas('status', 'Website settings updated.');

    expect($website->fresh()->primaryDomain()?->domain)->toBe('www.correct-example.com')
        ->and($website->fresh()->seo_history_backfilled_at)->toBeNull()
        ->and($website->domains()->count())->toBe(1);
});

it('does not allow a website domain assigned to another website', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'original.example.com', 'is_primary' => true]);
    $otherWebsite = Website::factory()->create();
    $otherWebsite->domains()->create(['domain' => 'claimed.example.com', 'is_primary' => true]);

    $this->actingAs($admin)
        ->from(route('admin.websites.show', [$website, 'tab' => 'settings']))
        ->put(route('admin.websites.update', $website), [
            'name' => $website->name,
            'domain' => 'www.claimed.example.com',
        ])
        ->assertRedirect(route('admin.websites.show', [$website, 'tab' => 'settings']))
        ->assertSessionHasErrors('domain');

    expect($website->fresh()->primaryDomain()?->domain)->toBe('original.example.com');
});

it('lets an administrator choose the WordPress and Pixel connection workspaces', function (): void {
    config(['forms.pixel_ui_enabled' => true]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create([
        'wordpress_enabled' => false,
        'pixel_enabled' => true,
        'pixel_payload_version' => 4,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.websites.update', $website), [
            'wordpress_enabled' => '1',
            'pixel_enabled' => '0',
        ])
        ->assertRedirect(route('admin.websites.show', ['website' => $website, 'tab' => 'settings']))
        ->assertSessionHasNoErrors();

    expect($website->fresh())
        ->wordpress_enabled->toBeTrue()
        ->pixel_enabled->toBeFalse()
        ->pixel_payload_version->toBe(5);

    $this->actingAs($admin)
        ->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('data-tab="wordpress"', false)
        ->assertDontSee('data-tab="pixel"', false)
        ->assertSee('Enable the WordPress connection')
        ->assertSee('Enable the Pixel connection');
});
