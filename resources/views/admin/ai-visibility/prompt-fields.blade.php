@php($values = $values ?? [])
<label class="block text-sm font-medium text-slate-700">Recommendation prompt
    <textarea name="prompt" required maxlength="500" rows="2" class="mt-2 w-full rounded-lg border-slate-300 text-base sm:text-sm">{{ old('prompt', data_get($values, 'prompt')) }}</textarea>
</label>
<div class="grid gap-4 sm:grid-cols-2">
    <label class="block text-sm font-medium text-slate-700">Service or topic<input name="topic" maxlength="180" value="{{ data_get($values, 'topic') }}" class="mt-2 w-full rounded-lg border-slate-300 text-base sm:text-sm"></label>
    <label class="block text-sm font-medium text-slate-700">Location<input name="location" maxlength="180" value="{{ data_get($values, 'location') }}" class="mt-2 w-full rounded-lg border-slate-300 text-base sm:text-sm"></label>
    <label class="block text-sm font-medium text-slate-700">Priority<select name="priority" class="mt-2 w-full rounded-lg border-slate-300 text-base sm:text-sm"><option value="normal">Normal</option><option value="high" @selected(data_get($values, 'priority') === 'high')>High</option></select></label>
    <label class="block text-sm font-medium text-slate-700">Related Google keyword<select name="seo_target_keyword_id" class="mt-2 w-full rounded-lg border-slate-300 text-base sm:text-sm"><option value="">None</option>@foreach ($keywords as $keyword)<option value="{{ $keyword->id }}" @selected(data_get($values, 'seo_target_keyword_id') == $keyword->id)>{{ $keyword->term }}</option>@endforeach</select></label>
</div>
<input type="hidden" name="active" value="0">
<label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="active" value="1" @checked(data_get($values, 'active', true)) class="rounded border-slate-300 text-teal-600">Track this prompt</label>
