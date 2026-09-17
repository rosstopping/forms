@php($values = $values ?? [])
<label class="ui-label block">Recommendation prompt
    <textarea name="prompt" required maxlength="500" rows="2" class="ui-input mt-2 w-full">{{ old('prompt', data_get($values, 'prompt')) }}</textarea>
</label>
<div class="grid gap-4 sm:grid-cols-2">
    <label class="ui-label block">Service or topic<input name="topic" maxlength="180" value="{{ data_get($values, 'topic') }}" class="ui-input mt-2 w-full"></label>
    <label class="ui-label block">Location<input name="location" maxlength="180" value="{{ data_get($values, 'location') }}" class="ui-input mt-2 w-full"></label>
    <label class="ui-label block">Priority<select name="priority" class="ui-input mt-2 w-full"><option value="normal">Normal</option><option value="high" @selected(data_get($values, 'priority') === 'high')>High</option></select></label>
    <label class="ui-label block">Related Google keyword<select name="seo_target_keyword_id" class="ui-input mt-2 w-full"><option value="">None</option>@foreach ($keywords as $keyword)<option value="{{ $keyword->id }}" @selected(data_get($values, 'seo_target_keyword_id') == $keyword->id)>{{ $keyword->term }}</option>@endforeach</select></label>
</div>
<input type="hidden" name="active" value="0">
<label class="ui-label flex items-center gap-2"><input type="checkbox" name="active" value="1" @checked(data_get($values, 'active', true))>Track this prompt</label>
