@php
    $scoreGroups = [
        'On-page SEO' => ['Search essentials', 'Structured data'],
        'Crawl access' => ['Discoverability'],
        'Usability' => ['Accessibility'],
        'Security' => ['Security'],
        'Response time' => ['Availability & speed'],
    ];
@endphp
<div class="border-t border-ink/10 pt-6">
<div data-audit-report-surface class="grid overflow-hidden rounded-2xl bg-white">
    <div class="@container grid gap-3 px-4 py-5 sm:px-6 sm:py-6">
        <dl class="flex flex-wrap items-start gap-x-4 gap-y-4 sm:gap-x-8">
            @foreach ($scoreGroups as $label => $categories)
                @php
                    $checks = $findings->filter(fn (mixed $finding): bool => is_array($finding) && in_array($finding['category'] ?? null, $categories, true) && in_array($finding['severity'] ?? null, ['passed', 'warning', 'failed'], true));
                    if ($label === 'Response time') {
                        $checks = $checks->where('key', 'response_time');
                    }
                    $score = $checks->isNotEmpty() ? (int) round($checks->where('severity', 'passed')->count() / $checks->count() * 100) : null;
                    $grade = $score === null ? '—' : match (true) {
                        $score >= 95 => 'A+', $score >= 85 => 'A', $score >= 70 => 'B', $score >= 50 => 'C', $score >= 25 => 'D', default => 'F',
                    };
                @endphp
                <div data-audit-category-score data-category="{{ $label }}" data-score="{{ $score }}" class="grid w-max min-w-14 justify-items-center gap-2 sm:min-w-16">
                    <dt class="order-last whitespace-nowrap text-center text-xs leading-4 font-medium text-ink/65" title="{{ $label }}">{{ $label }}</dt>
                    <dd class="relative grid size-14 place-items-center text-base tabular-nums sm:size-16">
                        <svg viewBox="0 0 100 100" fill="none" aria-hidden="true" @class(['absolute inset-0 size-full -rotate-90', 'text-emerald-600' => $score !== null && $score >= 85, 'text-amber-500' => $score !== null && $score >= 50 && $score < 85, 'text-rose-500' => $score !== null && $score < 50, 'text-ink/15' => $score === null])>
                            <circle cx="50" cy="50" r="43" stroke="currentColor" stroke-width="7" class="opacity-15" />
                            @if ($score !== null && $score > 0)
                                <circle cx="50" cy="50" r="43" stroke="currentColor" stroke-width="7" stroke-linecap="round" pathLength="100" stroke-dasharray="{{ $score }} 100" />
                            @endif
                        </svg>
                        <span class="font-medium text-ink">{{ $grade }}</span>
                        <span class="sr-only">{{ $score !== null ? $score.'% of '.$checks->count().' checks passed' : 'Not checked' }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
    <section id="audit-unlock" data-audit-email-gate class="relative isolate grid min-h-152 items-start overflow-hidden px-3 pt-6 pb-8 sm:px-8 sm:pt-8 sm:pb-12" aria-labelledby="audit-email-title">
        <div data-audit-report-preview aria-hidden="true" inert class="pointer-events-none absolute inset-0 -z-20 grid select-none content-start gap-5 p-4 opacity-75 blur-[3px] sm:grid-cols-2 sm:p-6">
            <section class="grid gap-4 rounded-xl bg-white p-5 ring-1 ring-ink/10 sm:col-span-2">
                <h3 class="text-xl font-medium tracking-tight">Google rankings</h3>
                <div class="grid grid-cols-[3fr_1fr_1fr] gap-4 border-b border-ink/10 pb-3 text-base text-ink/55 sm:text-sm"><p>Search term</p><p>Position</p><p>Searches</p></div>
                @foreach (range(1, 5) as $row)
                    <div class="grid grid-cols-[3fr_1fr_1fr] gap-4 border-b border-ink/5 pb-3"><div class="h-3 w-4/5 rounded bg-ink/15"></div><div class="h-3 w-1/2 rounded bg-ink/10"></div><div class="h-3 w-2/3 rounded bg-ink/10"></div></div>
                @endforeach
            </section>
            @foreach (['Competitors in search', 'AI visibility', 'Website issues', 'Recommended next steps'] as $label)
                <section class="grid gap-4 rounded-xl bg-white p-5 ring-1 ring-ink/10">
                    <h3 class="text-xl font-medium tracking-tight">{{ $label }}</h3>
                    @foreach (range(1, 4) as $row)
                        <div class="grid grid-cols-[3fr_1fr] gap-4 border-t border-ink/5 pt-3"><div class="h-3 w-4/5 rounded bg-ink/15"></div><div class="h-3 rounded bg-garden/15"></div></div>
                    @endforeach
                </section>
            @endforeach
        </div>
        <div aria-hidden="true" class="absolute inset-0 -z-10 bg-white/20"></div>
        <div class="mx-auto grid w-full max-w-sm gap-5 rounded-2xl bg-white p-5 shadow-xl ring-1 ring-ink/10 sm:p-6">
            <div class="grid gap-2">
                <h2 id="audit-email-title" class="text-2xl font-medium tracking-tight text-balance">Where should we send it?</h2>
                <p class="text-pretty text-base text-ink/65">Enter your email to open the full report.</p>
            </div>
                <form method="POST" action="{{ route('marketing.website-audits.email-report', $audit) }}" data-audit-email-form class="grid w-full gap-3">
                    @csrf
                    <div class="absolute -left-[9999px]" aria-hidden="true"><label for="audit-email-check">Leave this blank</label><input id="audit-email-check" type="text" name="_sitewell_check" tabindex="-1" autocomplete="off"></div>
                    <div class="grid gap-2">
                        <label for="audit-report-email" class="text-base font-medium">Email address</label>
                        <input id="audit-report-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required maxlength="255" @if ($errors->has('email')) aria-invalid="true" aria-describedby="audit-email-error" @endif class="min-h-14 w-full rounded-xl bg-white px-4 text-base text-ink ring-1 ring-ink/20 outline-none placeholder:text-ink/40 focus-visible:ring-2 focus-visible:ring-garden" placeholder="you@example.com">
                        @error('email') <p id="audit-email-error" role="alert" class="text-base text-rose-700">{{ $message }}</p> @enderror
                    </div>
                    <input type="hidden" name="engagement_visit_id" value="">
                    <button type="submit" class="inline-flex min-h-14 items-center justify-center rounded-full bg-garden px-5 py-3 text-base font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden">View my report</button>
                    <div class="flex items-start gap-3 text-base text-ink/50 sm:text-sm">
                        <div class="flex h-lh shrink-0 items-center">
                            <span class="group inline-grid size-5 grid-cols-1 sm:size-4">
                                <input id="audit-marketing-consent" type="checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent')) class="col-start-1 row-start-1 appearance-none rounded-sm border border-ink/20 bg-white checked:border-garden checked:bg-garden indeterminate:border-garden indeterminate:bg-garden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden disabled:border-ink/20 disabled:bg-lichen disabled:checked:bg-lichen forced-colors:appearance-auto">
                                <svg viewBox="0 0 14 14" fill="none" aria-hidden="true" class="pointer-events-none col-start-1 row-start-1 size-7/8 self-center justify-self-center stroke-white group-has-disabled:stroke-ink/25"><path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="group-not-has-checked:opacity-0" /><path d="M3 7H11" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="group-not-has-indeterminate:opacity-0" /></svg>
                            </span>
                        </div>
                        <label for="audit-marketing-consent" class="min-w-0">{{ \App\Models\WebsiteAudit::MARKETING_CONSENT_TEXT }}</label>
                    </div>
                    @error('marketing_consent') <p role="alert" class="text-base text-rose-700">{{ $message }}</p> @enderror
                    <p class="text-base text-ink/55 sm:text-sm"><a href="{{ route('marketing.privacy') }}" class="underline underline-offset-4">Privacy policy</a></p>
                </form>
            <div class="flex items-start gap-3 border-t border-ink/10 pt-4">
                <img src="{{ asset('ross-topping.jpg') }}" alt="" width="36" height="36" class="size-9 shrink-0 rounded-full object-cover outline-1 -outline-offset-1 outline-black/5">
                <p class="text-pretty text-base text-ink/60 sm:text-sm">I’m Ross, I run Sitewell. If you want a hand with anything we find, I can sort it for you.</p>
            </div>
        </div>
    </section>
</div>
</div>
