<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Missing service page finder | Digizu</title>
    <meta name="description" content="Find services your business mentions without a dedicated page. Enter your website for a free check from Digizu.">
    @if ($state)<meta name="robots" content="noindex, nofollow">@else<link rel="canonical" href="{{ route('tools.service-page-finder.create') }}">@endif
    @vite(['resources/css/app.css'])
</head>
<body class="marketing-site bg-white text-ink antialiased">
    <a href="#main" class="sr-only focus:not-sr-only">Skip to content</a>
    <header class="border-b border-ink/10"><div class="mx-auto flex max-w-5xl items-center justify-between gap-5 px-5 py-6 sm:px-10"><a href="https://digizu.co.uk/" class="text-2xl font-semibold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-4">digizu.</a><a href="https://digizu.co.uk/contact/" class="text-base underline underline-offset-4 sm:text-sm">Talk to Ross</a></div></header>
    <main id="main" class="mx-auto max-w-5xl px-5 py-12 sm:px-10 sm:py-20">
    @if (! $state)
        <h1 class="max-w-[25ch] text-4xl font-medium tracking-tight text-balance sm:text-6xl">Find services that need their own page.</h1>
        <p class="mt-6 max-w-[56ch] text-lg text-ink/75">Your website may mention services customers struggle to find. We’ll read your pages and look for services without a dedicated page.</p>
        <form method="POST" action="{{ route('tools.service-page-finder.store') }}" class="mt-9 grid max-w-2xl gap-3">
            @csrf
            <label for="website_url" class="text-base font-medium">Website address</label>
            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                <input id="website_url" name="website_url" type="text" required maxlength="255" inputmode="url" autocomplete="url" autocapitalize="none" spellcheck="false" placeholder="example.co.uk" value="{{ old('website_url') }}" aria-invalid="{{ $errors->has('website_url') ? 'true' : 'false' }}" @error('website_url') aria-describedby="url-error" @enderror class="min-h-14 w-full min-w-0 rounded-xl border border-ink/25 px-4 text-base focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink">
                <button type="submit" class="min-h-14 rounded-xl bg-ink px-6 py-3 text-base font-medium text-white hover:bg-ink/85 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ink">Find missing pages</button>
            </div>
            @error('website_url')<p id="url-error" role="alert" class="text-base text-red-700">{{ $message }}</p>@enderror
            <p class="text-base text-ink/65 sm:text-sm">Free. No account or email address needed.</p>
            <div class="sr-only" aria-hidden="true"><label for="website-check">Leave this field empty</label><input id="website-check" name="_sitewell_check" tabindex="-1" autocomplete="off"></div>
            @if ($turnstileEnabled)<div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" data-theme="light"></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endif
        </form>
        <section class="mt-16 border-t border-ink/10 pt-8"><h2 class="text-2xl font-medium tracking-tight">Suggestions grounded in your website.</h2><p class="mt-4 max-w-[65ch] text-base text-ink/75">We check up to eight pages and show where each service was mentioned. We look for existing coverage before suggesting a new page. Some websites block automated checks or load content we can’t read.</p><p class="mt-4 max-w-[65ch] text-base text-ink/75">This checks page coverage. It doesn’t measure search demand or predict rankings.</p></section>
    @elseif (in_array($state['status'], ['queued', 'running'], true))
        <h1 class="max-w-[25ch] text-4xl font-medium tracking-tight sm:text-5xl">Reading your website.</h1><p class="mt-5 break-words text-lg text-ink/70">{{ $state['url'] }}</p><p role="status" class="mt-6 max-w-[56ch] text-base">We’re checking service mentions and looking for pages that already cover them. This can take a few minutes.</p>
        <p id="check-delay" class="mt-4 hidden text-base">The check is taking longer than usual. You can reload this page to check its progress.</p>
        <a href="{{ route('tools.service-page-finder.show', $id) }}" class="mt-6 inline-block underline underline-offset-4">Refresh progress</a>
        <script>
        (() => {
            let attempts = 0;
            const timer = setInterval(async () => {
                if (++attempts > 150) { clearInterval(timer); document.getElementById('check-delay').classList.remove('hidden'); return; }
                try {
                    const response = await fetch(@json(route('tools.service-page-finder.status', $id)), { headers: { Accept: 'application/json' }, cache: 'no-store' });
                    if (!response.ok) { clearInterval(timer); document.getElementById('check-delay').classList.remove('hidden'); return; }
                    const data = await response.json();
                    if (!['queued', 'running'].includes(data.status)) { clearInterval(timer); location.reload(); }
                } catch { document.getElementById('check-delay').classList.remove('hidden'); }
            }, 4000);
        })();
        </script>
    @elseif ($state['status'] === 'failed')
        <h1 class="text-4xl font-medium tracking-tight">We couldn’t complete this check.</h1><p class="mt-5 max-w-[56ch] text-base">The website may block automated access, or the analysis service may be unavailable. Try again or ask Ross to take a look.</p><a href="{{ route('tools.service-page-finder.create') }}" class="mt-6 inline-block underline underline-offset-4">Try another check</a>
    @else
        @php($result = $state['result'])
        <h1 class="max-w-[25ch] text-4xl font-medium tracking-tight text-balance sm:text-5xl">Your service page opportunities.</h1><p class="mt-4 break-words text-lg text-ink/70">{{ $state['url'] }}</p>
        <p class="mt-5 max-w-[65ch] text-base text-ink/75">We checked {{ count($result['pages']) }} pages on {{ \Illuminate\Support\Carbon::parse($result['checked_at'])->format('j F Y') }}. Suggestions apply to the pages we could read. Other pages may already cover these services.</p>
        @if ($result['suggestions'] === [])
            <section class="mt-10 border-t border-ink/10 pt-7"><h2 class="text-2xl font-medium">{{ $result['supported'] ? 'No clear gaps found in this sample.' : 'Not enough service information to make a useful suggestion.' }}</h2><p class="mt-4 max-w-[65ch] text-base text-ink/75">This doesn’t establish that your website covers every service. The pages below show the scope of this check.</p></section>
        @endif
        @foreach ($result['suggestions'] as $suggestion)
            <article class="mt-10 border-t border-ink/10 pt-7"><h2 class="text-2xl font-medium tracking-tight">{{ $suggestion['title'] }}</h2><p class="mt-3 text-base text-ink/75">We found a mention of {{ $suggestion['service'] }}, but no clear dedicated page in the sample.</p><blockquote class="mt-5 border-l-2 border-ink/25 pl-5 text-lg">“{{ $suggestion['quote'] }}”</blockquote><a href="{{ $suggestion['source_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block break-all text-base underline underline-offset-4 sm:text-sm">View source page ↗</a>
            @if ($suggestion['questions'])<h3 class="mt-6 text-lg font-medium">Questions the page could answer</h3><ul class="mt-3 list-disc space-y-2 pl-5 text-base">@foreach ($suggestion['questions'] as $question)<li>{{ $question }}</li>@endforeach</ul>@endif
            </article>
        @endforeach
        @if ($result['covered'])<section class="mt-10 border-t border-ink/10 pt-7"><h2 class="text-2xl font-medium">Existing service pages found.</h2><ul class="mt-4 space-y-3">@foreach ($result['covered'] as $service)<li><a href="{{ $service['url'] }}" target="_blank" rel="noopener noreferrer" class="underline underline-offset-4">{{ $service['service'] }} ↗</a></li>@endforeach</ul></section>@endif
        <details class="mt-10 border-t border-ink/10 pt-7"><summary class="cursor-pointer text-lg font-medium">Pages checked</summary><ul class="mt-4 space-y-3">@foreach ($result['pages'] as $page)<li class="break-all"><a href="{{ $page['url'] }}" target="_blank" rel="noopener noreferrer" class="text-base underline underline-offset-4 sm:text-sm">{{ $page['url'] }}</a></li>@endforeach</ul><p class="mt-4 max-w-[65ch] text-base text-ink/70">{{ $result['partial'] ? 'This was a limited sample. Some pages were not checked.' : 'These are the pages discovered and read by this check.' }} Scripts were not run. We haven’t measured search demand or verified whether adding a page would improve rankings.</p></details>
        <section class="mt-14 rounded-2xl bg-ink/5 p-6 sm:p-9"><h2 class="max-w-[40ch] text-2xl font-medium tracking-tight">Get help with your service pages.</h2><p class="mt-4 max-w-[60ch] text-base text-ink/75">Ross can review the suggestions, check relevant searches and discuss writing and improving your pages through Digizu’s monthly SEO service.</p><a href="https://digizu.co.uk/contact/" class="mt-6 inline-flex min-h-12 items-center rounded-xl bg-ink px-6 py-3 text-base font-medium text-white hover:bg-ink/85 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ink">Discuss my service pages</a></section>
        <a href="{{ route('tools.service-page-finder.create') }}" class="mt-7 inline-block underline underline-offset-4">Check another website</a>
    @endif
    </main>
    <footer class="mx-auto max-w-5xl px-5 py-8 text-base text-ink/65 sm:px-10 sm:text-sm">A free website tool from <a href="https://digizu.co.uk/" class="underline underline-offset-4">Digizu</a>.</footer>
</body>
</html>
