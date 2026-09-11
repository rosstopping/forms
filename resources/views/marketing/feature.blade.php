@extends('layouts.marketing')

@section('title', $feature['eyebrow'])
@section('meta_description', $feature['description'])

@section('content')
    <section class="border-b border-ink/10 py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">{{ $feature['eyebrow'] }}</p>
            <h1 class="mt-5 max-w-[19ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">{{ $feature['title'] }}</h1>
            <p class="mt-6 max-w-[58ch] text-pretty text-lg leading-8 text-ink/65">{{ $feature['description'] }}</p>
            <div class="mt-8 flex flex-wrap items-center gap-5">
                <a href="{{ route('marketing.contact') }}" class="rounded-md bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss sm:text-sm">Talk to us</a>
                <a href="{{ route('marketing.free-site-audit') }}" class="text-base font-medium underline decoration-ink/20 underline-offset-4 hover:decoration-ink sm:text-sm">Start with a free audit →</a>
            </div>
        </div>
    </section>

    <section class="py-20 sm:py-28">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">The problem</p>
                <h2 class="mt-4 max-w-[22ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ $feature['problem_title'] }}</h2>
                <p class="mt-5 max-w-[48ch] text-pretty text-base leading-7 text-ink/60">{{ $feature['problem'] }}</p>
            </div>
            <dl class="grid gap-8 sm:grid-cols-2">
                @foreach ($feature['outcomes'] as [$title, $copy])
                    <div class="border-t border-ink/15 pt-5"><dt class="font-display text-2xl font-semibold tracking-tight">{{ $title }}</dt><dd class="mt-3 text-pretty text-base leading-7 text-ink/60 sm:text-sm sm:leading-6">{{ $copy }}</dd></div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="border-y border-ink/10 bg-lichen/45 py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">How it works</p>
            <h2 class="mt-4 font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">From problem to practical progress</h2>
            <dl class="mt-12 grid gap-8 lg:grid-cols-3">
                @foreach ($feature['steps'] as $index => [$title, $copy])
                    <div class="border-t border-ink/15 pt-5"><dt><span class="font-mono text-sm text-garden">0{{ $index + 1 }}</span><span class="mt-5 block font-display text-2xl font-semibold tracking-tight">{{ $title }}</span></dt><dd class="mt-3 text-pretty text-base leading-7 text-ink/60 sm:text-sm sm:leading-6">{{ $copy }}</dd></div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="py-20 sm:py-28">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div><p class="font-mono text-sm uppercase tracking-wide text-garden">A practical example</p><h2 class="mt-4 max-w-[21ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ $feature['example_title'] }}</h2></div>
            <div class="rounded-xl bg-ink p-7 text-paper shadow-xl sm:p-10"><p class="font-mono text-sm uppercase tracking-wide text-apricot">What this looks like</p><p class="mt-5 max-w-[58ch] text-pretty text-lg leading-8 text-paper/75">{{ $feature['example'] }}</p></div>
        </div>
    </section>

    <section class="border-t border-ink/10 py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">Questions, plainly answered</p>
            <h2 class="mt-4 font-display text-4xl font-semibold tracking-tight text-balance">What businesses usually ask</h2>
            <div class="mt-10 max-w-4xl divide-y divide-ink/10 border-y border-ink/10">
                @foreach ($feature['faqs'] as [$question, $answer])
                    <details class="group py-5"><summary class="flex list-none items-center justify-between gap-6 text-base font-medium sm:text-sm">{{ $question }}<span class="font-mono text-garden group-open:hidden">+</span><span class="hidden font-mono text-garden group-open:inline">−</span></summary><p class="mt-4 max-w-[70ch] text-pretty text-base text-ink/60 sm:text-sm">{{ $answer }}</p></details>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
