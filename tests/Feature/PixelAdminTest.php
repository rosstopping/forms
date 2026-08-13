<?php

use App\Models\Optimisation;
use App\Models\PixelPageSighting;
use App\Models\User;
use App\Models\Website;

it('shows configured pixel installation and connection information', function (): void {
    config([
        'services.sitewell.pixel_asset_url' => 'https://cdn.sitewell.test/pixel.js',
        'services.sitewell.pixel_api_url' => 'https://api.sitewell.test/api/pixel',
    ]);
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create([
        'pixel_last_seen_at' => now()->subMinutes(2),
        'pixel_last_seen_url' => 'https://example.com/services',
        'pixel_last_seen_hostname' => 'example.com',
        'pixel_version' => '1.0.0',
    ]);
    PixelPageSighting::factory()->count(2)->for($website)->create();
    Optimisation::factory()->for($website)->create(['status' => 'deployed']);
    Optimisation::factory()->for($website)->create(['status' => 'draft']);

    $this->actingAs($user)
        ->get(route('admin.websites.show', ['website' => $website, 'tab' => 'pixel']))
        ->assertSuccessful()
        ->assertSee('data-tab="pixel"', false)
        ->assertSee('data-tab-panel="pixel"', false)
        ->assertSee('Sitewell Pixel')
        ->assertSee('Connected')
        ->assertSee('2 minutes ago')
        ->assertSee('Pages detected')
        ->assertSee('Active optimisations')
        ->assertSee('https://cdn.sitewell.test/pixel.js')
        ->assertSee('https://api.sitewell.test/api/pixel')
        ->assertSee('data-site=&quot;'.$website->pixel_public_key.'&quot;', false)
        ->assertSee('Copy snippet')
        ->assertSee('Content Security Policy');
});

it('does not expose another website pixel connection to an unrelated user', function (): void {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();

    $this->actingAs($outsider)
        ->get(route('admin.websites.show', ['website' => $website, 'tab' => 'pixel']))
        ->assertForbidden();
});
