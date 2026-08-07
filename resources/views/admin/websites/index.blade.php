@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Websites</h1>
            <p class="text-sm text-slate-600">Manage the websites that can submit forms.</p>
        </div>
        <div class="flex items-center gap-2">
            @if (Auth::user()?->isAdmin())
                <a href="{{ route('admin.websites.create') }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Add website</a>
            @endif
            <a href="{{ route('admin.dashboard') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to dashboard</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Name</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Status</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Forms</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Submissions</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Seen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($websites as $website)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.websites.show', $website) }}" class="font-medium text-slate-900 hover:text-slate-700">{{ e($website->name) }}</a>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $website->is_active ? 'Active' : 'Disabled' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $website->forms_count }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $website->submissions_count }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $website->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">No websites yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $websites->links() }}
</div>
@endsection
