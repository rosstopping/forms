@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Submission details</h1>
            <p class="text-sm text-slate-600">Inspect the submitted data and source information.</p>
        </div>
        <a href="{{ route('admin.form-submissions.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to submissions</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Lead overview</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Source domain</dt><dd class="font-medium">{{ e($formSubmission->source_domain ?: 'Unknown') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Source URL</dt><dd class="font-medium break-all">{{ e($formSubmission->source_url ?: 'Unknown') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Form</dt><dd class="font-medium">{{ e($formSubmission->form?->name ?: 'Unknown form') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Website</dt><dd class="font-medium">{{ e($formSubmission->website?->name ?: 'Unknown website') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd class="font-medium"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $formSubmission->resolvedStatusLabel() }}</span></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Owner</dt><dd class="font-medium">{{ e($formSubmission->assignee?->name ?: 'Unassigned') }}</dd></div>
            </dl>

            <form method="POST" action="{{ route('admin.form-submissions.update', $formSubmission) }}" class="mt-5 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="status">Status</label>
                    <select id="status" name="status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach (['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost'] as $value => $label)
                            <option value="{{ $value }}" @selected($formSubmission->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- <div>
                    <label class="block text-sm font-medium text-slate-700" for="assigned_to">Assigned to</label>
                    <select id="assigned_to" name="assigned_to" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Unassigned</option>
                        @foreach (App\Models\User::query()->orderBy('name')->get() as $user)
                            <option value="{{ $user->id }}" @selected($formSubmission->assigned_to === $user->id)>{{ e($user->name) }}</option>
                        @endforeach
                    </select>
                </div> --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="4" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $formSubmission->notes) }}</textarea>
                </div>
                <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Save lead</button>
            </form>
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Submission data</h2>
            <pre class="mt-3 overflow-x-auto rounded bg-slate-50 p-3 text-xs text-slate-700">{{ json_encode($formSubmission->data ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>
</div>
@endsection
