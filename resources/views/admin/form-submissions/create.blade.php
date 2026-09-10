@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <h1 class="text-2xl font-semibold">Add lead</h1>
        <p class="mt-1 text-sm text-slate-600">Record an enquiry for {{ $website->name }}. You can add tags and manage follow-ups after saving.</p>
    </div>
    <form method="POST" action="{{ route('admin.form-submissions.store') }}" class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
        @csrf
        <input type="hidden" name="website_id" value="{{ $website->id }}">
        @error('website_id')<p class="text-sm text-red-700">The selected website changed. Check the website above before adding this lead.</p>@enderror
        @include('admin.form-submissions.manual-fields', ['contactData' => []])
        <div>
            <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
            <select id="status" name="status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @foreach (\App\Models\FormSubmission::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected(old('status', 'new') === $value)>{{ $label }}</option>@endforeach
            </select>
            @error('status')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="follow_up_at" class="block text-sm font-medium text-slate-700">Follow up</label>
            <input id="follow_up_at" name="follow_up_at" type="datetime-local" value="{{ is_string(old('follow_up_at')) ? old('follow_up_at') : '' }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error('follow_up_at')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="notes" class="block text-sm font-medium text-slate-700">Internal notes</label>
            <textarea id="notes" name="notes" rows="3" maxlength="10000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ is_string(old('notes')) ? old('notes') : '' }}</textarea>
            @error('notes')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Add lead</button>
            <a href="{{ route('admin.form-submissions.index') }}" class="rounded-md border px-4 py-2 text-sm text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
