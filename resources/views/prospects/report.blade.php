<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Website review · {{ $prospect->business_name }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-slate-50 text-slate-900">
    @php
        $checks = collect($prospect->findings ?? []);
        $passedChecks = $checks->where('severity', 'passed')->count();
        $warningChecks = $checks->where('severity', 'warning')->count();
        $failedChecks = $checks->where('severity', 'failed')->count();
        $categories = $checks->groupBy(fn (array $check): string => $check['category'] ?? 'Website review');
    @endphp
    <main class="mx-auto max-w-4xl space-y-6 p-5 sm:p-10">
        <header class="rounded-2xl bg-slate-950 p-6 text-white shadow-sm sm:p-8">
            <p class="text-sm font-medium text-teal-300">Website review</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $prospect->business_name }}</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-300">We reviewed publicly visible website signals across availability, speed, search essentials, accessibility, structured data, security, and discoverability. This is a helpful starting point, not a diagnosis or a replacement for private analytics.</p>
            @if ($prospect->analysed_at)<p class="mt-4 text-xs text-slate-400">Prepared {{ $prospect->analysed_at->format('j F Y') }}</p>@endif
        </header>

        <section class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2"><p class="text-sm font-medium text-slate-500">At a glance</p><p class="mt-2 text-2xl font-semibold">{{ $failedChecks > 0 ? $failedChecks.' '.Str::plural('item', $failedChecks).' to prioritise' : ($warningChecks > 0 ? $warningChecks.' '.Str::plural('opportunity', $warningChecks).' worth reviewing' : 'The checks look healthy') }}</p><p class="mt-2 text-sm leading-6 text-slate-600">{{ $checks->count() }} public website checks completed across six key areas.</p></div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"><p class="text-sm font-medium text-emerald-800">Looking good</p><p class="mt-2 text-3xl font-semibold text-emerald-900">{{ $passedChecks }}</p><p class="mt-1 text-xs text-emerald-800">checks passed</p></div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5"><p class="text-sm font-medium text-amber-800">To review</p><p class="mt-2 text-3xl font-semibold text-amber-900">{{ $warningChecks + $failedChecks }}</p><p class="mt-1 text-xs text-amber-800">opportunities found</p></div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-xl font-semibold">Your website audit</h2><p class="mt-1 text-sm text-slate-600">Each area shows what is working well alongside the opportunities we found.</p>
            <div class="mt-6 space-y-8">
                @forelse ($categories as $category => $categoryChecks)
                    <section><div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3"><h3 class="font-semibold">{{ $category }}</h3><p class="text-xs text-slate-500">{{ $categoryChecks->where('severity', 'passed')->count() }} passed · {{ $categoryChecks->whereIn('severity', ['warning', 'failed'])->count() }} to review</p></div><div class="mt-4 grid gap-3 sm:grid-cols-2">@foreach ($categoryChecks as $finding)<article class="rounded-xl border p-4 {{ $finding['severity'] === 'passed' ? 'border-emerald-100 bg-emerald-50/50' : ($finding['severity'] === 'failed' ? 'border-red-200 bg-red-50/50' : 'border-amber-200 bg-amber-50/50') }}"><div class="flex items-center gap-2"><span class="size-2 rounded-full {{ $finding['severity'] === 'passed' ? 'bg-emerald-500' : ($finding['severity'] === 'failed' ? 'bg-red-500' : 'bg-amber-400') }}"></span><h4 class="text-sm font-semibold">{{ $finding['title'] }}</h4></div><p class="mt-2 text-sm leading-6 text-slate-600">{{ $finding['message'] }}</p></article>@endforeach</div></section>
                @empty
                    <p class="rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600">The website review is still being prepared. Please try this link again shortly.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl bg-teal-900 p-6 text-white shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-8 sm:p-8" aria-labelledby="audit-next-step-title">
            <div>
                <p class="text-sm font-medium text-teal-200">Your next step</p>
                <h2 id="audit-next-step-title" class="mt-1 text-2xl font-semibold">Want help turning these findings into fixes?</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-teal-50/80">Talk through the priorities with Ross, or send us a message if you would prefer to start by email.</p>
            </div>
            <div class="mt-5 flex flex-wrap gap-3 sm:mt-0 sm:shrink-0">
                <a href="{{ route('marketing.contact') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-teal-950 hover:bg-teal-50">Get in touch</a>
                <a href="{{ config('marketing.booking_url') }}" class="rounded-md px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/30 hover:bg-white/10">Book a call with Ross</a>
            </div>
        </section>

        <p class="px-2 text-center text-xs leading-5 text-slate-500">This review is based on publicly available information from {{ parse_url($prospect->website_url, PHP_URL_HOST) }}. If you would like to discuss any finding, reply to the email that shared this report.</p>
    </main>
</body>
</html>
