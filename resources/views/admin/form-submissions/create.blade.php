@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <h1 class="text-2xl font-semibold">Add lead</h1>
        <p class="mt-1 text-slate-600 text-base sm:text-sm">Record an enquiry for {{ $website->name }}. You can add tags and manage follow-ups after saving.</p>
    </div>
    <form method="POST" action="{{ route('admin.form-submissions.store') }}" class="ui-panel ui-section space-y-4">
        @csrf
        <input type="hidden" name="website_id" value="{{ $website->id }}">
        @error('website_id')<p class="text-red-700 text-base sm:text-sm">The selected website changed. Check the website above before adding this lead.</p>@enderror
        @include('admin.form-submissions.manual-fields', ['contactData' => []])
        <div>
            <label for="status" class="ui-label block">Status</label>
            <select id="status" name="status" class="ui-input mt-1 w-full">
                @foreach (\App\Models\FormSubmission::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected(old('status', 'new') === $value)>{{ $label }}</option>@endforeach
            </select>
            @error('status')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="follow_up_at" class="ui-label block">Follow up</label>
            <input id="follow_up_at" name="follow_up_at" type="datetime-local" value="{{ is_string(old('follow_up_at')) ? old('follow_up_at') : '' }}" class="ui-input mt-1 w-full">
            @error('follow_up_at')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="notes" class="ui-label block">Internal notes</label>
            <textarea id="notes" name="notes" rows="3" maxlength="10000" class="ui-input mt-1 w-full">{{ is_string(old('notes')) ? old('notes') : '' }}</textarea>
            @error('notes')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3">
            <button type="submit" class="ui-button ui-button-primary">Add lead</button>
            <a href="{{ route('admin.form-submissions.index') }}" class="ui-button ui-button-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
