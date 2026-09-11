<button type="submit" {{ $attributes->merge(['class' => 'relative w-full rounded-lg bg-garden px-3 py-3 text-base font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:py-2 sm:text-sm']) }}>
    {{ $slot }}
    <span class="pointer-events-none absolute top-1/2 left-1/2 h-[max(100%,3rem)] w-full -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span>
</button>
