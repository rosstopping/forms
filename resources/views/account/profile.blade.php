@extends('layouts.app')

@section('content')
<div class="max-w-3xl space-y-6">
    <header>
        <p class="font-medium uppercase tracking-widest text-teal-700 text-base sm:text-sm">Your account</p>
        <h1 class="mt-1 text-2xl font-semibold text-slate-950">Profile</h1>
        <p class="mt-1 text-slate-600 text-base sm:text-sm">Update your name, email address, or password.</p>
    </header>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.profile.update') }}" class="ui-panel ui-section space-y-6">
        @csrf
        @method('PUT')

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="profile-name" class="ui-label block">Name</label>
                <input id="profile-name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required class="ui-input mt-1 w-full">
                @error('name')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="profile-email" class="ui-label block">Email address</label>
                <input id="profile-email" type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email" required class="ui-input mt-1 w-full">
                @error('email')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="border-t border-slate-950/10 pt-6">
            <h2 class="font-semibold text-slate-950">Change password</h2>
            <p class="mt-1 text-slate-600 text-base sm:text-sm">Leave these fields blank to keep your existing password.</p>
            <div class="mt-4 grid gap-5 sm:grid-cols-3">
                <div>
                    <label for="current-password" class="ui-label block">Current password</label>
                    <input id="current-password" type="password" name="current_password" autocomplete="current-password" class="ui-input mt-1 w-full">
                    @error('current_password')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="new-password" class="ui-label block">New password</label>
                    <input id="new-password" type="password" name="password" autocomplete="new-password" class="ui-input mt-1 w-full">
                    @error('password')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password-confirmation" class="ui-label block">Confirm password</label>
                    <input id="password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" class="ui-input mt-1 w-full">
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="ui-button ui-button-primary">Save profile</button>
        </div>
    </form>
</div>
@endsection
