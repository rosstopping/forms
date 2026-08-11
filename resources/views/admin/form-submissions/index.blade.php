@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Submissions</h1>
            <p class="text-sm text-slate-600">Review form enquiries and track the follow-up status for this website.</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Lead</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Form</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Status</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Owner</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Received</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($submissions as $submission)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.form-submissions.show', $submission) }}" class="font-medium text-slate-900 hover:text-slate-700">{{ e($submission->source_domain ?: 'Unknown') }}</a>
                            <div class="mt-1 text-xs text-slate-500">{{ e($submission->website?->name ?: 'Unknown website') }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ e($submission->form?->name ?: 'Unknown form') }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $submission->resolvedStatusLabel() }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ e($submission->assignee?->name ?: 'Unassigned') }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $submission->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">No submissions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $submissions->links() }}
</div>
@endsection
