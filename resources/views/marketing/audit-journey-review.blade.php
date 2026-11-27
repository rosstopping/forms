@php($journeyReview = data_get($audit->insights, 'journey_review'))
<section data-audit-journey-review class="grid gap-6 border-t border-ink/10 pt-8" aria-labelledby="audit-journey-title">
    <div class="grid gap-2">
        <h2 id="audit-journey-title" class="text-2xl font-medium tracking-tight text-balance">Visitor journey</h2>
        <p class="max-w-[65ch] text-pretty text-base text-ink/65">Mobile setup, enquiry routes, content and forms found in this page’s HTML. Each observation includes its evidence and a suggested next check.</p>
    </div>
    @if (data_get($journeyReview, 'status') === 'completed')
        <div class="grid gap-2 rounded-xl bg-lichen p-4">
            <p class="max-w-[80ch] text-base text-ink/75 sm:text-sm">{{ $journeyReview['limitations'] }}</p>
            @if ($journeyReview['partial'] ?? false)
                <p class="text-base text-amber-900 sm:text-sm">Partial review. Only the first 2 MB of returned HTML was inspected.</p>
            @endif
            <p class="text-base text-ink/65 sm:text-sm">Captured {{ \Illuminate\Support\Carbon::parse($journeyReview['captured_at'])->format('j M Y, H:i T') }}.</p>
        </div>
        <div class="grid divide-y divide-ink/10">
            @foreach ($journeyReview['findings'] ?? [] as $journeyFinding)
                <article class="grid min-w-0 gap-4 py-6 first:pt-0 last:pb-0 sm:grid-cols-[11rem_minmax(0,1fr)]">
                    <div class="grid content-start gap-2">
                        <p class="font-medium text-ink">{{ $journeyFinding['category'] }}</p>
                        <p @class(['text-base sm:text-sm', 'text-amber-900' => $journeyFinding['status'] === 'Review suggested', 'text-ink/65' => $journeyFinding['status'] !== 'Review suggested'])>{{ $journeyFinding['status'] }}</p>
                    </div>
                    <div class="grid min-w-0 gap-3">
                        <h3 class="text-xl font-medium tracking-tight">{{ $journeyFinding['title'] }}</h3>
                        <p class="max-w-[70ch] text-pretty text-base wrap-anywhere text-ink/75">{{ $journeyFinding['observation'] }}</p>
                        <div class="grid gap-1">
                            <p class="text-base font-medium sm:text-sm">Suggested next check</p>
                            <p class="max-w-[70ch] text-pretty text-base text-ink/65">{{ $journeyFinding['recommendation'] }}</p>
                        </div>
                        <details class="min-w-0">
                            <summary class="min-h-11 cursor-pointer py-2 font-medium text-garden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden">View HTML evidence</summary>
                            <dl class="grid min-w-0 gap-3 rounded-xl bg-lichen p-4 text-base sm:text-sm">
                                <div class="grid gap-1"><dt class="font-medium">Source page</dt><dd class="wrap-anywhere text-ink/75">{{ $journeyReview['source_url'] }}</dd></div>
                                <div class="grid gap-1"><dt class="font-medium">Element query</dt><dd class="wrap-anywhere font-mono text-ink/75">{{ data_get($journeyFinding, 'evidence.selector') }}</dd></div>
                                <div class="grid gap-1"><dt class="font-medium">Extracted evidence</dt><dd class="whitespace-pre-wrap wrap-anywhere text-ink/75">{{ data_get($journeyFinding, 'evidence.excerpt') }}</dd></div>
                            </dl>
                        </details>
                    </div>
                </article>
            @endforeach
        </div>
        @if ($journeyReview['routes'] ?? [])
            <section class="grid gap-4 border-t border-ink/10 pt-6" aria-labelledby="audit-enquiry-routes-title">
                <h3 id="audit-enquiry-routes-title" class="text-xl font-medium tracking-tight">Potential enquiry and booking routes</h3>
                <p class="max-w-[65ch] text-base text-ink/65">Up to eight matching links. Labels suggest their purpose. Link destinations haven’t been tested.</p>
                <dl class="grid gap-4 sm:grid-cols-2">
                    @foreach ($journeyReview['routes'] as $journeyRoute)
                        <div class="grid min-w-0 content-start gap-1 border-l-2 border-ink/10 pl-4">
                            <dt class="font-medium wrap-anywhere">{{ $journeyRoute['label'] }}</dt>
                            <dd class="text-base wrap-anywhere text-ink/65 sm:text-sm">{{ $journeyRoute['destination'] }}</dd>
                            <dd class="text-base text-ink/65 sm:text-sm">{{ $journeyRoute['type'] }} · Destination not tested</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif
        <details class="min-w-0 border-t border-ink/10 pt-4">
            <summary class="min-h-11 cursor-pointer py-2 text-base font-medium text-garden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Capture record</summary>
            <p class="pt-2 text-base wrap-anywhere text-ink/65 sm:text-sm">HTML sample SHA-256: {{ $journeyReview['sample_sha256'] }}</p>
            <p class="pt-2 text-base text-ink/65 sm:text-sm">Any website preview above is a separate desktop capture. It isn’t evidence of a completed mobile or booking test.</p>
        </details>
    @elseif (data_get($journeyReview, 'status') === 'unavailable')
        <p class="max-w-[65ch] text-base text-ink/65">{{ $journeyReview['unavailable_reason'] }}</p>
    @else
        <p class="max-w-[65ch] text-base text-ink/65">A visitor journey review wasn’t captured for this audit. It will be included when a new website audit runs. Opening this report doesn’t run new checks.</p>
    @endif
</section>
