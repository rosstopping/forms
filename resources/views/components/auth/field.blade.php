@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])

<div class="grid gap-2">
    <label for="{{ $name }}" class="ui-label">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password') value="{{ $value }}" @endif
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error"
        @elseif ($hint) aria-describedby="{{ $name }}-hint" @endif
        {{ $attributes->class(['ui-input w-full']) }}>
    @if ($hint)<p id="{{ $name }}-hint" class="text-pretty text-base text-ink/60 sm:text-sm">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $name }}-error" role="alert" class="text-pretty text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror
</div>
