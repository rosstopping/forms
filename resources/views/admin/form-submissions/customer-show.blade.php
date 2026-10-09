@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <x-portal-notices :validation-errors="session('errors', $errors)" />
    <header><h1 class="text-2xl font-semibold">Lead details</h1><p class="mt-2 text-sm text-slate-600">{{ $formSubmission->website->name }} · {{ $formSubmission->created_at->format('j M Y') }}</p><a href="{{ route('admin.form-submissions.index') }}" class="ui-button ui-button-secondary mt-3">Back to leads</a></header>
    <section class="ui-panel ui-section"><h2 class="font-semibold">Enquiry</h2><dl class="mt-4 space-y-3">@foreach($formSubmission->data ?? [] as $key => $value)<div><dt class="text-sm font-medium text-slate-600">{{ str($key)->replace('_', ' ')->title() }}</dt><dd class="mt-1 whitespace-pre-wrap break-words text-sm">{{ is_array($value) ? implode(', ', array_filter($value, 'is_scalar')) : (is_scalar($value) ? $value : '') }}</dd></div>@endforeach</dl></section>
    <form method="POST" action="{{ route('admin.form-submissions.update', $formSubmission) }}" class="ui-panel ui-section space-y-4">@csrf @method('PUT')
        <div><label for="lead-status" class="ui-label">Status</label><select id="lead-status" name="status" class="ui-input mt-1">@foreach(\App\Models\FormSubmission::STATUS_LABELS as $key => $label)<option value="{{ $key }}" @selected(old('status', $formSubmission->status) === $key)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="lead-notes" class="ui-label">Notes</label><textarea id="lead-notes" name="notes" maxlength="10000" rows="5" class="ui-input mt-1 w-full">{{ is_string(old('notes', $formSubmission->notes)) ? old('notes', $formSubmission->notes) : '' }}</textarea></div>
        <fieldset><legend class="ui-label">Tags</legend><input type="hidden" name="tags_present" value="1"><div class="mt-2 flex flex-wrap gap-4">@foreach($leadTags as $tag)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" @checked(in_array($tag->id, (is_array(old('tag_ids')) ? old('tag_ids') : $formSubmission->tags->modelKeys())))>{{ $tag->name }}</label>@endforeach</div></fieldset>
        <div><label for="lead-new-tag" class="ui-label">New tag</label><input id="lead-new-tag" name="new_tag" value="{{ is_string(old('new_tag')) ? old('new_tag') : '' }}" maxlength="40" class="ui-input mt-1"></div>
        <button class="ui-button ui-button-primary">Save lead</button>
    </form>
</div>
@endsection
