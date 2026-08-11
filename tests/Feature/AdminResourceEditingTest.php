<?php

use App\Models\Form;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Hash;

it('allows an administrator to edit a user and optionally change their password', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create([
        'name' => 'Original name',
        'email' => 'original@example.com',
        'role' => User::ROLE_USER,
        'password' => 'original-password',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.edit', $user))
        ->assertSuccessful()
        ->assertSee('Edit user')
        ->assertSee('original@example.com');

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => 'Updated name',
            'email' => 'updated@example.com',
            'role' => User::ROLE_ADMIN,
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->name)->toBe('Updated name')
        ->and($user->email)->toBe('updated@example.com')
        ->and($user->role)->toBe(User::ROLE_ADMIN)
        ->and(Hash::check('replacement-password', $user->password))->toBeTrue();
});

it('keeps a users password when the password fields are left blank', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create(['password' => 'original-password']);
    $originalPassword = $user->password;

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => 'New display name',
            'email' => $user->email,
            'role' => $user->role,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertSessionDoesntHaveErrors();

    expect($user->fresh()->password)->toBe($originalPassword);
});

it('prevents normal users from editing user accounts', function (): void {
    $normalUser = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($normalUser)
        ->get(route('admin.users.edit', $otherUser))
        ->assertForbidden();

    $this->actingAs($normalUser)
        ->put(route('admin.users.update', $otherUser), [
            'name' => 'Unauthorised change',
            'email' => $otherUser->email,
            'role' => User::ROLE_USER,
        ])
        ->assertForbidden();
});

it('allows an administrator to rename and delete a website', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create(['name' => 'Original website']);
    $form = Form::factory()->for($website)->create();

    $this->actingAs($admin)
        ->put(route('admin.websites.update', $website), [
            'name' => 'Renamed website',
            'user_id' => $website->user_id,
            'health_reports_enabled' => false,
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('admin.websites.show', $website));

    expect($website->fresh()->name)->toBe('Renamed website');

    $this->actingAs($admin)
        ->delete(route('admin.websites.destroy', $website))
        ->assertRedirect(route('admin.websites.index'));

    $this->assertModelMissing($website);
    $this->assertModelMissing($form);
});

it('prevents website owners from deleting websites', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->delete(route('admin.websites.destroy', $website))
        ->assertForbidden();

    $this->assertModelExists($website);
});
