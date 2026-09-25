<label class="flex min-h-12 items-center gap-3 text-base sm:text-sm" for="{{ $field }}">
    <input type="hidden" name="{{ $field }}" value="0">
    <input id="{{ $field }}" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, data_get($values, 'delivery.'.$field)))>
    {{ $label }}
</label>
