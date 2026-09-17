<button type="submit" {{ $attributes->merge(['class' => 'ui-button ui-button-primary relative w-full']) }}>
    {{ $slot }}
    <span class="pointer-events-none absolute top-1/2 left-1/2 h-[max(100%,3rem)] w-full -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span>
</button>
