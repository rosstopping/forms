@php
    $fieldRows = $rows ?? 3;
    $fieldRequired = $required ?? false;
    $fieldLimit = $maxlength ?? 2000;
@endphp
<div class="grid gap-2">
    <label for="{{ $field }}" class="ui-label">{{ $label }}@unless ($fieldRequired) <span class="font-normal text-slate-500">(optional)</span>@endunless</label>
    @if ($fieldRows === 1)
        <input id="{{ $field }}" name="{{ $field }}" type="text" value="{{ old($field, data_get($values, $step.'.'.$field)) }}" maxlength="{{ $fieldLimit }}" @required($fieldRequired) @if ($errors->has($field)) aria-invalid="true" @endif @if (! empty($hint)) aria-describedby="{{ $field }}-hint" @endif class="ui-input w-full">
    @else
        <textarea id="{{ $field }}" name="{{ $field }}" rows="{{ $fieldRows }}" maxlength="{{ $fieldLimit }}" @required($fieldRequired) @if ($errors->has($field)) aria-invalid="true" @endif @if (! empty($hint)) aria-describedby="{{ $field }}-hint" @endif class="ui-input w-full">{{ old($field, data_get($values, $step.'.'.$field)) }}</textarea>
    @endif
    @if (! empty($hint))<p id="{{ $field }}-hint" class="text-base text-pretty text-slate-500 sm:text-sm">{{ $hint }}</p>@endif
</div>
