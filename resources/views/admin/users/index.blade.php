@extends('layouts.app')

@section('content')
<div class="space-y-6">
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
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $user->created_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.users.edit', $user) }}" class="text-sm font-medium text-slate-700 hover:text-slate-950">Edit</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">No users yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
@endsection
