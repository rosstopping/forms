@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Form submissions</h1>
            <p class="text-sm text-slate-600">Review the latest submissions received by your forms.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to dashboard</a>
    </div>

    <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Source</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Form</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Website</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Spam</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Received</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($submissions as $submission)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.form-submissions.show', $submission) }}" class="font-medium text-slate-900 hover:text-slate-700">{{ e($submission->source_domain ?: 'Unknown') }}</a>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ e($submission->form?->name ?: 'Unknown form') }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ e($submission->website?->name ?: 'Unknown website') }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $submission->is_spam ? 'Yes' : 'No' }}</td>
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
