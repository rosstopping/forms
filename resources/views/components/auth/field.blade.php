@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])

<div class="grid gap-2">
    <label for="{{ $name }}" class="text-base font-medium sm:text-sm">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password') value="{{ $value }}" @endif
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error"
        @elseif ($hint) aria-describedby="{{ $name }}-hint" @endif
        {{ $attributes->class(['w-full rounded-lg border-0 bg-white px-3 py-3 text-base shadow-xs ring-1 placeholder:text-ink/40 hover:ring-ink/35 focus:outline-2 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm', 'ring-ink/20' => ! $errors->has($name), 'ring-red-600/60' => $errors->has($name)]) }}>
    @if ($hint)<p id="{{ $name }}-hint" class="text-pretty text-base text-ink/60 sm:text-sm">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $name }}-error" role="alert" class="text-pretty text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror
</div>
