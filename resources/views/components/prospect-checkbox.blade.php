@props(['name', 'id', 'checked' => false])
<span class="group inline-grid size-5 shrink-0 grid-cols-1 sm:size-4">
    <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="1" @checked($checked) class="col-start-1 row-start-1 appearance-none rounded-sm border border-slate-300 bg-white checked:border-teal-700 checked:bg-teal-700 indeterminate:border-teal-700 indeterminate:bg-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:border-slate-300 disabled:bg-slate-100 disabled:checked:bg-slate-100 forced-colors:appearance-auto">
    <svg viewBox="0 0 14 14" fill="none" class="pointer-events-none col-start-1 row-start-1 size-7/8 self-center justify-self-center stroke-white group-has-disabled:stroke-slate-950/25" aria-hidden="true">
        <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="group-not-has-checked:opacity-0" />
        <path d="M3 7H11" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="group-not-has-indeterminate:opacity-0" />
    </svg>
</span>
