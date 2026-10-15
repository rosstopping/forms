<div class="grid gap-2">
    <p data-plan-{{ $copyKey }} class="{{ $copyClass }} text-pretty text-ink/65 {{ $document[$copyKey.'_more'] ? 'print:hidden' : '' }}">{{ $document[$copyKey] }}</p>
    @if($document[$copyKey.'_more'])
        <div class="text-base sm:text-sm print:hidden">
            <button type="button" class="cursor-pointer font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:text-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden" aria-haspopup="dialog" aria-controls="plan-{{ $copyKey }}-dialog" onclick="document.getElementById('plan-{{ $copyKey }}-dialog').showModal()">Read more<span class="sr-only">: {{ $copyTitle }}</span></button>
        </div>
        <p data-plan-{{ $copyKey }}-print class="{{ $copyClass }} hidden text-pretty text-ink/65 print:block">{{ $document[$copyKey.'_full'] }}</p>
        <dialog id="plan-{{ $copyKey }}-dialog" aria-labelledby="plan-{{ $copyKey }}-title" class="fixed inset-0 m-auto max-h-[80dvh] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-2xl bg-white p-6 text-ink shadow-xl backdrop:bg-ink/40 sm:p-8 print:hidden">
            <div class="grid gap-5">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="plan-{{ $copyKey }}-title" class="text-xl font-medium text-balance">{{ $copyTitle }}</h2>
                    <form method="dialog" class="shrink-0 text-base sm:text-sm"><button type="submit" autofocus class="cursor-pointer font-medium text-garden underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden">Close</button></form>
                </div>
                <p class="whitespace-pre-line text-base text-pretty text-ink/70">{{ $document[$copyKey.'_full'] }}</p>
            </div>
        </dialog>
    @endif
</div>
