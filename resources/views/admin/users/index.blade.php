@extends('layouts.app')

@section('content')
<div class="space-y-6">
    @if ($errors->has('user'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first('user') }}</div>
    @endif
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Users</h1>
            <p class="text-sm text-slate-600">Manage administrator and client accounts.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.users.create') }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Create user</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Name</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Email</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Role</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Membership</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Onboarding call</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Created</th>
                    <th class="px-4 py-3 text-right text-sm font-semibold text-slate-700"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $user->isAdmin() ? 'Admin' : 'User' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            {{ data_get($plans, $user->effectiveMembershipTier().'.name', 'None') }}
                            @if ($user->hasAdminManagedMembership())<span class="text-xs text-teal-700">(admin managed)</span>@endif
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            @if ($user->onboarding_status === 'trial_active')
                                <form method="POST" action="{{ route('admin.users.onboarding-call.update', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label for="onboarding-call-{{ $user->id }}" class="sr-only">Onboarding call status for {{ $user->name }}</label>
                                    <select id="onboarding-call-{{ $user->id }}" name="status" onchange="this.form.submit()" class="rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs">
                                        <option value="not_booked" @selected(! $user->onboarding_call_booked_at)>Not booked</option>
                                        <option value="booked" @selected($user->onboarding_call_booked_at && ! $user->onboarding_call_completed_at)>Booked</option>
                                        <option value="completed" @selected($user->onboarding_call_completed_at)>Completed</option>
                                    </select>
                                    @if ($user->onboarding_call_booking_started_at && ! $user->onboarding_call_booked_at)
                                        <p class="mt-1 text-xs text-amber-700">Booking started</p>
                                    @endif
                                </form>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $user->created_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.users.edit', $user) }}" class="text-sm font-medium text-slate-700 hover:text-slate-950">Edit</a>
                                @unless (Auth::user()->is($user))
                                    @if ($user->canBeImpersonated())
                                        <form method="POST" action="{{ route('admin.users.impersonate.store', $user) }}">
                                            @csrf
                                            <button type="submit" class="text-sm font-medium text-teal-700 hover:text-teal-900">View as user</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-700 hover:text-red-900">Delete</button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">No users yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
@endsection
