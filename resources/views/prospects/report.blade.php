<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Website review · {{ $prospect->business_name }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-slate-50 text-slate-900">
    <main class="mx-auto max-w-4xl space-y-6 p-5 sm:p-10">
        <header class="rounded-2xl bg-slate-950 p-6 text-white shadow-sm sm:p-8">
            <p class="text-sm font-medium text-teal-300">Website review</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $prospect->business_name }}</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-300">We reviewed a small number of publicly visible website checks. The findings below are intended as helpful opportunities to consider, not a diagnosis or a list of urgent problems.</p>
            @if ($prospect->analysed_at)<p class="mt-4 text-xs text-slate-400">Prepared {{ $prospect->analysed_at->format('j F Y') }}</p>@endif
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-xl font-semibold">What we found</h2><p class="mt-1 text-sm text-slate-600">A few clear opportunities from the homepage review.</p></div>@if ($prospect->opportunity_score !== null)<div class="rounded-xl bg-amber-50 px-4 py-2 text-center"><p class="text-2xl font-semibold text-amber-800">{{ $prospect->opportunity_score }}</p><p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">Opportunity score</p></div>@endif</div>
            <div class="mt-6 space-y-4">
                @forelse ($prospect->findings ?? [] as $finding)
                    <article class="rounded-xl border border-slate-200 p-4"><div class="flex items-center gap-2"><span class="size-2 rounded-full {{ $finding['severity'] === 'failed' ? 'bg-red-500' : 'bg-amber-400' }}"></span><h3 class="font-semibold">{{ $finding['title'] }}</h3></div><p class="mt-2 text-sm leading-6 text-slate-600">{{ $finding['message'] }}</p></article>
                @empty
                    <p class="rounded-xl bg-emerald-50 p-4 text-sm leading-6 text-emerald-900">The homepage covers the checks in this short review well. There may still be useful opportunities elsewhere on the website.</p>
                @endforelse
            </div>
        </section>

        <p class="px-2 text-center text-xs leading-5 text-slate-500">This review is based on publicly available information from {{ parse_url($prospect->website_url, PHP_URL_HOST) }}. If you would like to discuss any finding, reply to the email that shared this report.</p>
    </main>
</body>
</html>
