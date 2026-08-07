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
        ->and($website->primaryDomain()?->domain)->toBe('example.com');
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
