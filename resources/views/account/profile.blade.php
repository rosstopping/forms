@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <header>
        <p class="text-xs font-medium uppercase tracking-widest text-teal-700">Your account</p>
        <h1 class="mt-1 text-2xl font-semibold text-slate-950">Profile</h1>
        <p class="mt-1 text-sm text-slate-600">Update your name, email address, or password.</p>
    </header>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf
        @method('PUT')

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="profile-name" class="block text-sm font-medium text-slate-900">Name</label>
                <input id="profile-name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="profile-email" class="block text-sm font-medium text-slate-900">Email address</label>
                <input id="profile-email" type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="border-t border-slate-200 pt-6">
            <h2 class="font-semibold text-slate-950">Change password</h2>
            <p class="mt-1 text-sm text-slate-600">Leave these fields blank to keep your existing password.</p>
            <div class="mt-4 grid gap-5 sm:grid-cols-3">
                <div>
                    <label for="current-password" class="block text-sm font-medium text-slate-900">Current password</label>
                    <input id="current-password" type="password" name="current_password" autocomplete="current-password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('current_password')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="new-password" class="block text-sm font-medium text-slate-900">New password</label>
                    <input id="new-password" type="password" name="password" autocomplete="new-password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('password')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password-confirmation" class="block text-sm font-medium text-slate-900">Confirm password</label>
                    <input id="password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save profile</button>
        </div>
    </form>
</div>
@endsection
