@extends('layouts.marketing')

@section('title', 'Getting started with '.$audit->domain)
@section('meta_description', 'Your private Sitewell website review.')

@section('content')
@php
    $checks = collect($audit->findings ?? []);
    $passedChecks = $checks->where('severity', 'passed')->count();
    $reviewChecks = $checks->whereIn('severity', ['warning', 'failed'])->count();
    $categories = $checks->groupBy(fn (array $check): string => $check['category'] ?? 'Website review');
@endphp
<main class="isolate border-b border-ink/10 py-12 sm:py-20">
    <div class="mx-auto grid max-w-5xl gap-8 px-5 sm:px-8 lg:px-10">
        <header class="grid gap-4 border-b border-ink/10 pb-8">
            <p class="font-mono text-sm font-medium uppercase tracking-wide text-moss">Getting started</p>
            <h1 class="max-w-[24ch] break-words font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ $audit->domain }}</h1>
            <p class="max-w-[48ch] text-pretty text-base text-ink/65 sm:text-sm">{{ $audit->isReadyToDisplay() ? 'We reviewed publicly visible signals across website health, search essentials, accessibility, security, and discoverability.' : 'We are reviewing publicly visible signals across website health, search essentials, accessibility, security, and discoverability.' }}</p>
        </header>
        @if ($audit->status !== \App\Models\WebsiteAudit::STATUS_FAILED && ! $audit->isReadyToDisplay())
            <section id="audit-progress" data-status-url="{{ route('marketing.website-audits.status', $audit) }}" class="grid gap-8 rounded-2xl bg-[#fffefa] p-6 shadow-xl ring-1 ring-ink/10 sm:p-8" aria-labelledby="audit-progress-title" aria-live="polite">
                <div class="grid gap-2">
                    <h2 id="audit-progress-title" class="max-w-[35ch] font-display text-3xl font-semibold tracking-tight text-balance">Reviewing your website</h2>
                    <p class="max-w-[56ch] text-pretty text-base text-ink/65 sm:text-sm">This normally takes less than a minute. You can leave this page open while Sitewell works.</p>
                </div>
                <ol class="grid gap-0" role="list">
                    @foreach (['Reaching your website', 'Checking security and trust signals', 'Reviewing search essentials', 'Checking accessibility and structure', 'Preparing your recommendations'] as $index => $stage)
                        <li data-audit-stage="{{ $index }}" class="flex min-w-0 items-start gap-4 border-t border-ink/10 py-4 first:border-t-0 first:pt-0 last:pb-0" aria-current="{{ $index === 0 ? 'step' : 'false' }}">
                            <span class="mt-0.5 size-4 shrink-0 rounded-full border-2 border-ink/20 data-current:border-garden data-current:bg-garden" data-stage-marker></span>
                            <p class="min-w-0 text-base text-ink/45 data-current:font-medium data-current:text-ink sm:text-sm" data-stage-label>{{ $stage }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>
            <script>
                (() => {
                    const progress = document.getElementById('audit-progress');
                    const stages = Array.from(progress.querySelectorAll('[data-audit-stage]'));
                    const minimumVisibleUntil = Date.now() + 10000;
                    let activeStage = 0;
                    const showStage = (stage) => stages.forEach((item, index) => {
                        const current = index === stage;
                        item.setAttribute('aria-current', current ? 'step' : 'false');
                        item.querySelector('[data-stage-marker]').toggleAttribute('data-current', current);
                        item.querySelector('[data-stage-label]').toggleAttribute('data-current', current);
                    });
                    showStage(activeStage);
                    window.setInterval(() => {
                        activeStage = Math.min(activeStage + 1, stages.length - 1);
                        showStage(activeStage);
                    }, 1800);
                    const checkStatus = async () => {
                        try {
                            const response = await fetch(progress.dataset.statusUrl, { headers: { Accept: 'application/json' } });
                            if (! response.ok) return;
                            const result = await response.json();
                            if (result.completed || result.failed) {
                                window.setTimeout(() => window.location.reload(), Math.max(0, minimumVisibleUntil - Date.now()));
                            }
                        } catch (_) {
                            // A temporary polling failure should not interrupt the visible progress state.
                        }
                    };
                    window.setInterval(checkStatus, 2000);
                })();
            </script>
        @elseif ($audit->status === \App\Models\WebsiteAudit::STATUS_FAILED)
            <section class="grid gap-4 rounded-2xl bg-[#fffefa] p-6 shadow-xl ring-1 ring-ink/10 sm:p-8">
                <h2 class="max-w-[35ch] font-display text-3xl font-semibold tracking-tight text-balance">We could not review your website</h2>
                <p class="max-w-[56ch] text-pretty text-base text-ink/65 sm:text-sm">The website may be unavailable or blocking automated checks. Please check the address and try again.</p>
                <p><a href="{{ route('marketing.free-site-audit') }}" class="font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Try another website address</a></p>
            </section>
        @else
            <section class="grid gap-4 border-t border-ink/10 pt-7" aria-labelledby="audit-next-step-title">
                <p class="font-mono text-sm font-medium uppercase tracking-wide text-moss">Your next step</p>
                <h2 id="audit-next-step-title" class="max-w-[35ch] font-display text-3xl font-semibold tracking-tight text-balance">Turn these findings into a fix plan</h2>
                <p class="max-w-[56ch] text-pretty text-base text-ink/65 sm:text-sm">Enter your email address below to get started fixing your website.</p>
                @if (session('claim_status'))
                    <p class="max-w-[56ch] rounded-lg bg-lichen p-4 text-pretty text-base text-ink sm:text-sm">{{ session('claim_status') }}</p>
                @else
                    <form method="POST" action="{{ route('marketing.website-audits.claim', $audit) }}" class="grid max-w-xs gap-4">
                        @csrf
                        <div class="grid gap-2">
                            <label for="email" class="text-base font-medium sm:text-sm">Email address</label>
                            <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" class="w-full rounded-md border-0 bg-white px-3 py-3 text-base text-ink shadow-sm ring-1 ring-ink/15 placeholder:text-ink/35 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm">
                            @error('email')<p class="text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="rounded-md bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-garden sm:text-sm">Start preparing my fixes</button>
                    </form>
                @endif
            </section>
            <section class="grid gap-7">
                <div class="grid gap-5 border-b border-ink/10 pb-7 sm:grid-cols-3">
                    @foreach ([['Checks completed', $checks->count(), 'text-ink'], ['Looking good', $passedChecks, 'text-garden'], ['Worth reviewing', $reviewChecks, 'text-amber-700']] as [$label, $value, $colour])
                        <div class="grid gap-1 sm:border-r sm:border-ink/10 sm:pr-5 sm:last:border-r-0">
                            <p class="text-base text-ink/55 sm:text-sm">{{ $label }}</p>
                            <p class="font-display text-4xl font-semibold tracking-tight tabular-nums {{ $colour }}">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="grid gap-10">
                    @foreach ($categories as $category => $categoryChecks)
                        <section class="grid gap-5">
                            <div class="flex min-w-0 x-wrap items-baseline justify-between gap-3 border-b border-ink/10 pb-3">
                                <h2 class="min-w-0 font-display text-2xl font-semibold tracking-tight text-balance">{{ $category }}</h2>
                                <p class="text-base text-ink/50 tabular-nums sm:text-sm">{{ $categoryChecks->where('severity', 'passed')->count() }} passed · {{ $categoryChecks->whereIn('severity', ['warning', 'failed'])->count() }} to review</p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                @foreach ($categoryChecks as $finding)
                                    <article class="grid gap-2 rounded-xl border border-ink/10 p-5">
                                        <div class="flex min-w-0 items-baseline gap-2">
                                            <span class="size-2 shrink-0 rounded-full {{ $finding['severity'] === 'passed' ? 'bg-garden' : ($finding['severity'] === 'failed' ? 'bg-red-600' : 'bg-amber-500') }}" aria-hidden="true"></span>
                                            <h3 class="min-w-0 font-medium text-balance">{{ $finding['title'] }}</h3>
                                        </div>
                                        <p class="text-pretty text-base text-ink/60 sm:text-sm">{{ $finding['message'] }}</p>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            </section>
        @endif
        <p class="text-pretty text-base text-ink/45 sm:text-sm">This private link expires {{ $audit->expires_at->diffForHumans() }}. The review uses public information from {{ $audit->domain }}.</p>
    </div>
</main>
@endsection
