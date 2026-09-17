@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Edit user</h1>
            <p class="text-slate-600 text-base sm:text-sm">Update account details and access.</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="ui-button ui-button-secondary">Back to users</a>
    </div>

    <div class="ui-panel ui-section">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="ui-label block" for="name">Name</label>
                <input id="name" name="name" type="text" required value="{{ old('name', $user->name) }}" class="ui-input mt-1 w-full">
                @error('name')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="ui-label block" for="email">Email</label>
                <input id="email" name="email" type="email" required value="{{ old('email', $user->email) }}" class="ui-input mt-1 w-full">
                @error('email')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="ui-label block" for="password">New password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" class="ui-input mt-1 w-full">
                    <p class="mt-1 text-slate-500 text-base sm:text-sm">Leave blank to keep the current password.</p>
                    @error('password')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="ui-label block" for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="ui-input mt-1 w-full">
                </div>
            </div>

            <div>
                <label class="ui-label block" for="role">Role</label>
                <select id="role" name="role" class="ui-input mt-1 w-full">
                    <option value="user" @selected(old('role', $user->role) === 'user')>User</option>
                    <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
                </select>
                @error('role')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="ui-label block" for="admin_membership_tier">Admin-managed membership</label>
                <select id="admin_membership_tier" name="admin_membership_tier" class="ui-input mt-1 w-full">
                    <option value="">No admin-managed membership</option>
                    @foreach ($plans as $tier => $plan)
                        <option value="{{ $tier }}" @selected(old('admin_membership_tier', $user->admin_membership_tier) === $tier)>{{ $plan['name'] }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-slate-500 text-base sm:text-sm">This overrides package access without creating or changing a Stripe subscription.</p>
                @error('admin_membership_tier')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="ui-label block" for="admin_membership_expires_at">Admin-managed access ends on</label>
                <input id="admin_membership_expires_at" name="admin_membership_expires_at" type="date" value="{{ old('admin_membership_expires_at', $user->admin_membership_expires_at?->format('Y-m-d')) }}" class="ui-input mt-1 w-full">
                <p class="mt-1 text-slate-500 text-base sm:text-sm">Leave blank for no expiry. For a six-month trial, choose a date six months from today. Access lasts through that date ({{ config('app.timezone') }}), then falls back to any active paid subscription.</p>
                @error('admin_membership_expires_at')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="ui-button ui-button-primary">Save changes</button>
        </form>
    </div>
</div>
@endsection
