<?php

use App\Models\Prospect;
use App\Models\ProspectingIndustryProfile;
use App\Models\ProspectingLocation;
use App\Models\User;

it('lets administrators manage prospecting industries and locations', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->post(route('admin.prospecting-industry-profiles.store'), [
        'name' => 'Garden rooms',
        'enabled' => '1',
        'priority' => 90,
        'estimated_customer_value' => 12000,
        'customer_value_band' => 'very_high',
        'service_keywords' => "garden rooms\noutdoor rooms",
        'search_keywords' => "garden rooms\nluxury garden rooms",
        'minimum_position' => 8,
        'maximum_position' => 50,
        'maximum_site_size' => 30,
        'automatic_import_score' => 70,
        'notes' => 'High-value local projects.',
    ])->assertRedirect(route('admin.prospecting-industry-profiles.index'));

    $profile = ProspectingIndustryProfile::query()->sole();
    expect($profile->slug)->toBe('garden-rooms')
        ->and($profile->service_keywords)->toBe(['garden rooms', 'outdoor rooms']);

    $this->post(route('admin.prospecting-locations.store'), [
        'name' => 'Harrogate',
        'enabled' => '1',
        'priority' => 60,
    ])->assertRedirect(route('admin.prospecting-industry-profiles.index'));
    expect(ProspectingLocation::query()->sole()->slug)->toBe('harrogate');
});

it('shows an industry-level summary from existing outreach fields', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $profile = ProspectingIndustryProfile::factory()->create(['name' => 'Kitchen companies']);
    ProspectingLocation::factory()->create(['name' => 'Doncaster']);
    Prospect::factory()->for($profile, 'industryProfile')->create([
        'approved_at' => now(),
        'sent_at' => now(),
        'replied_at' => now(),
        'converted_at' => now(),
        'lead_temperature' => 'hot',
    ]);

    $this->actingAs($admin)->get(route('admin.prospecting-industry-profiles.index'))
        ->assertSuccessful()
        ->assertSee('Prospecting strategy')
        ->assertSee('Kitchen companies')
        ->assertSee('1 found · 1 approved')
        ->assertSee('1 sent · 0 opens · 0 clicks · 1 replies')
        ->assertSee('0 warm · 1 hot · 1 customers · 100.0%')
        ->assertSee('Doncaster');
});

it('forbids non-administrators from prospecting strategy management', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.prospecting-industry-profiles.index'))->assertForbidden();
    $this->post(route('admin.prospecting-locations.store'), ['name' => 'Leeds'])->assertForbidden();
});
