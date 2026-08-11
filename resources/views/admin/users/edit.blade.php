@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Edit user</h1>
            <p class="text-sm text-slate-600">Update account details and access.</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to users</a>
    </div>

    <div class="rounded-lg border bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-slate-700" for="name">Name</label>
                <input id="name" name="name" type="text" required value="{{ old('name', $user->name) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
                <input id="email" name="email" type="email" required value="{{ old('email', $user->email) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="password">New password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Leave blank to keep the current password.</p>
                    @error('password')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700" for="role">Role</label>
                <select id="role" name="role" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="user" @selected(old('role', $user->role) === 'user')>User</option>
                    <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
                </select>
                @error('role')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save changes</button>
        </form>
    </div>
</div>
@endsection
