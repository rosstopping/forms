@php($journeyReview = data_get($report->metrics, 'journey_review'))
<section class="ui-panel ui-section grid gap-5" aria-labelledby="health-journey-title">
    <div class="grid gap-2">
        <h2 id="health-journey-title" class="text-xl font-medium tracking-tight text-slate-950">Visitor journey review</h2>
        <p class="max-w-[75ch] text-base text-slate-600 sm:text-sm">Internal evidence for weekly SEO work. These observations are separate from the technical health score and haven’t been sent to the client.</p>
    </div>
    @if (data_get($journeyReview, 'status') === 'completed')
        <p class="max-w-[85ch] text-base text-slate-600 sm:text-sm">{{ $journeyReview['limitations'] }}</p>
        @if ($journeyReview['partial'] ?? false)
            <p class="text-base text-amber-900 sm:text-sm">Partial HTML sample. The report’s response-size limit was reached.</p>
        @endif
        <p class="text-base wrap-anywhere text-slate-600 sm:text-sm">Source: {{ $journeyReview['source_url'] }} · Captured {{ \Illuminate\Support\Carbon::parse($journeyReview['captured_at'])->format('j M Y, H:i T') }}.</p>
        <div class="grid divide-y divide-slate-950/10">
            @foreach ($journeyReview['findings'] ?? [] as $journeyFinding)
                <article class="grid min-w-0 gap-3 py-5 first:pt-0 last:pb-0">
                    <h3 class="text-lg font-medium wrap-anywhere text-slate-950">{{ $journeyFinding['title'] }}</h3>
                    <p class="text-base text-slate-600 sm:text-sm">{{ $journeyFinding['category'] }} · {{ $journeyFinding['status'] }}</p>
                    <p class="text-base wrap-anywhere text-slate-700 sm:text-sm">{{ $journeyFinding['observation'] }}</p>
                    <p class="text-base text-slate-600 sm:text-sm"><strong class="font-medium">Next check:</strong> {{ $journeyFinding['recommendation'] }}</p>
                    <details>
                        <summary class="min-h-11 cursor-pointer py-2 font-medium text-teal-800">View HTML evidence</summary>
                        <dl class="ui-well grid min-w-0 gap-3 p-4 text-base sm:text-sm">
                            <div class="grid gap-1"><dt class="font-medium">Element query</dt><dd class="font-mono wrap-anywhere">{{ data_get($journeyFinding, 'evidence.selector') }}</dd></div>
                            <div class="grid gap-1"><dt class="font-medium">Extracted evidence</dt><dd class="whitespace-pre-wrap wrap-anywhere">{{ data_get($journeyFinding, 'evidence.excerpt') }}</dd></div>
                        </dl>
                    </details>
                </article>
            @endforeach
        </div>
        @if ($journeyReview['routes'] ?? [])
            <details>
                <summary class="min-h-11 cursor-pointer py-2 font-medium text-teal-800">Potential enquiry and booking links</summary>
                <dl class="grid gap-3 sm:grid-cols-2">
                    @foreach ($journeyReview['routes'] as $journeyRoute)
                        <div class="grid min-w-0 gap-1"><dt class="font-medium wrap-anywhere">{{ $journeyRoute['label'] }}</dt><dd class="text-base wrap-anywhere text-slate-600 sm:text-sm">{{ $journeyRoute['destination'] }} · Destination not tested</dd></div>
                    @endforeach
                </dl>
            </details>
        @endif
    @else
        <p class="text-base text-slate-600 sm:text-sm">{{ data_get($journeyReview, 'unavailable_reason', 'Journey evidence wasn’t captured in this older report. The next weekly health report will include it.') }}</p>
    @endif
</section>
