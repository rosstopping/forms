@php($servicePages = data_get($audit->insights, 'service_pages'))
@if ($showDetails && is_array($servicePages))
<section class="grid gap-5 border-t border-ink/10 pt-8" aria-labelledby="audit-service-pages-title">
    <div class="grid gap-2">
        <h2 id="audit-service-pages-title" class="text-2xl font-medium tracking-tight text-balance">Service page opportunities.</h2>
        <p class="max-w-[65ch] text-base text-ink/65">Services your website mentions that may benefit from their own page.</p>
    </div>
    @if (in_array($servicePages['status'], ['queued', 'running'], true))
        <p role="status" class="text-base text-ink/65">Reading your service pages. Findings will appear here when the check finishes.</p>
    @elseif ($servicePages['status'] === 'completed')
        @php($coverage = $servicePages['result'])
        <p class="max-w-[65ch] text-base text-ink/65">Checked {{ count($coverage['pages']) }} pages on {{ \Illuminate\Support\Carbon::parse($coverage['checked_at'])->format('j M Y') }}. Other pages may already cover these services. This check doesn’t measure search demand or predict rankings.</p>
        @forelse ($coverage['suggestions'] as $suggestion)
            <article class="grid min-w-0 gap-3 border-t border-ink/10 pt-5">
                <h3 class="text-xl font-medium tracking-tight">{{ $suggestion['title'] }}</h3>
                <p class="max-w-[65ch] text-base text-ink/75">We found a mention of {{ $suggestion['service'] }}, but no clear dedicated page in the sample.</p>
                <blockquote class="border-l-2 border-ink/20 pl-4 text-base wrap-anywhere text-ink/75">“{{ $suggestion['quote'] }}”</blockquote>
                <a href="{{ $suggestion['source_url'] }}" target="_blank" rel="noopener noreferrer" class="w-fit text-base underline underline-offset-4 sm:text-sm">View source page ↗</a>
                @if ($suggestion['questions'])
                    <p class="text-base font-medium">Questions the page could answer</p>
                    <ul class="list-disc space-y-2 pl-5 text-base text-ink/75">@foreach ($suggestion['questions'] as $question)<li>{{ $question }}</li>@endforeach</ul>
                @endif
            </article>
        @empty
            <p class="max-w-[65ch] text-base text-ink/75">{{ $coverage['supported'] ? 'No clear service page gaps found in this sample.' : 'We couldn’t establish enough service information to make a useful suggestion.' }}</p>
        @endforelse
        @if ($coverage['covered'])
            <details><summary class="cursor-pointer text-base font-medium">Existing service pages found</summary><ul class="mt-3 space-y-2">@foreach ($coverage['covered'] as $covered)<li><a href="{{ $covered['url'] }}" target="_blank" rel="noopener noreferrer" class="text-base underline underline-offset-4 sm:text-sm">{{ $covered['service'] }} ↗</a></li>@endforeach</ul></details>
        @endif
        <details><summary class="cursor-pointer text-base font-medium">Pages checked</summary><ul class="mt-3 space-y-2">@foreach ($coverage['pages'] as $page)<li class="min-w-0 break-all"><a href="{{ $page['url'] }}" target="_blank" rel="noopener noreferrer" class="text-base underline underline-offset-4 sm:text-sm">{{ $page['url'] }}</a></li>@endforeach</ul><p class="mt-3 max-w-[65ch] text-base text-ink/65">{{ $coverage['partial'] ? 'This was a limited sample. Some pages were not checked.' : 'These are the pages discovered and read by this check.' }} Scripts were not run.</p></details>
    @else
        <p class="max-w-[65ch] text-base text-ink/65">The service page check couldn’t finish. Your other audit findings are still available.</p>
    @endif
</section>
@endif
