@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Create user</h1>
            <p class="text-sm text-slate-600">Create a new account that can be assigned to websites.</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to users</a>
    </div>

    <div class="rounded-lg border bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700" for="name">Name</label>
                <input id="name" name="name" type="text" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
                <input id="email" name="email" type="email" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="password">Password</label>
                    <input id="password" name="password" type="password" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700" for="role">Role</label>
                <select id="role" name="role" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="user" @selected(old('role') === 'user')>User</option>
                    <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                </select>
                @error('role')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700" for="admin_membership_tier">Admin-managed membership</label>
                <select id="admin_membership_tier" name="admin_membership_tier" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">No admin-managed membership</option>
                    @foreach ($plans as $tier => $plan)
                        <option value="{{ $tier }}" @selected(old('admin_membership_tier') === $tier)>{{ $plan['name'] }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Grants package access without creating or changing a Stripe subscription.</p>
                @error('admin_membership_tier')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Create account</button>
        </form>
    </div>
</div>
@endsection
