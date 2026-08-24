<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('links the signed in user name to their profile', function (): void {
    $user = User::factory()->create(['name' => 'Alex Morgan']);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertSee('href="'.route('admin.profile.edit').'"', false)
        ->assertSee('Alex Morgan');
});

it('allows users to update their account details and password', function (): void {
    $user = User::factory()->create(['password' => 'original-password']);

    $this->actingAs($user)
        ->put(route('admin.profile.update'), [
            'name' => 'Updated Person',
            'email' => 'updated-person@example.com',
            'current_password' => 'original-password',
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('admin.profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Updated Person')
        ->and($user->email)->toBe('updated-person@example.com')
        ->and(Hash::check('replacement-password', $user->password))->toBeTrue();
});

it('requires the current password before changing password', function (): void {
    $user = User::factory()->create(['password' => 'original-password']);

    $this->actingAs($user)
        ->from(route('admin.profile.edit'))
        ->put(route('admin.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'incorrect-password',
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('original-password', $user->fresh()->password))->toBeTrue();
});
