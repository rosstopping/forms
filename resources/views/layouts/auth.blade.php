<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <meta name="theme-color" content="#17201d">
    <title>@yield('title') · Sitewell</title>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white font-[InterVariable,sans-serif] text-ink [font-feature-settings:'cv02','cv03','cv04','cv11']">
    <div class="isolate grid min-h-dvh lg:grid-cols-[9fr_11fr]">
        <aside class="relative flex min-w-0 flex-col justify-between overflow-hidden bg-ink px-6 py-7 text-paper sm:px-10 lg:p-12 xl:p-16" aria-label="Sitewell">
            <div class="relative z-10 text-3xl font-display font-semibold tracking-tight">
                <a href="{{ route('marketing.home') }}" aria-label="Homepage" class="rounded-sm hover:text-white">Sitewell<span class="text-apricot">.</span></a>
            </div>
            <div class="pointer-events-none absolute -right-48 bottom-0 size-160 rounded-full border border-paper/10 max-lg:hidden" aria-hidden="true">
                <div class="absolute inset-12 rounded-full border border-paper/10"></div>
                <div class="absolute inset-24 rounded-full border border-paper/10"></div>
                <div class="absolute inset-36 rounded-full border border-paper/10 bg-moss/25"></div>
                <div class="absolute inset-48 rounded-full bg-lichen/10"></div>
            </div>
            <div class="relative z-10 grid gap-7 py-16 max-lg:hidden">
                <p class="font-mono text-sm uppercase tracking-wide text-lichen/75">A little care. A lot of growth.</p>
                <h2 class="max-w-[15ch] text-balance font-display text-5xl font-semibold tracking-tight xl:text-6xl">Your website,<br>well looked after.</h2>
                <p class="max-w-[34ch] text-pretty text-base/7 text-paper/70">A clearer picture of your website. A helping hand with what comes next.</p>
                <div class="flex items-center gap-3 pt-3 text-sm text-lichen">
                    <span class="size-2 shrink-0 rounded-full bg-apricot" aria-hidden="true"></span>
                    <p>More clarity. Less to worry about.</p>
                </div>
            </div>
            <p class="relative z-10 text-sm text-paper/50 max-lg:hidden">Website care, with your business in mind.</p>
        </aside>
        <div class="flex min-w-0 flex-col px-6 sm:px-10 lg:px-16">
            <header class="flex justify-end py-6 text-base text-ink/60 sm:py-8 sm:text-sm">
                <p>Need a hand? <a href="{{ route('marketing.contact') }}" class="font-medium text-moss underline-offset-4 hover:underline">Get in touch</a></p>
            </header>
            <main id="main-content" class="grid flex-1 content-center justify-items-center py-10 sm:py-16">
                <section class="grid w-full max-w-xs gap-7" aria-labelledby="auth-title">
                    <div class="grid gap-3">
                        <p class="font-mono text-base font-medium text-garden sm:text-sm">@yield('eyebrow', 'Your Sitewell account')</p>
                        <h1 id="auth-title" class="text-balance text-3xl font-semibold tracking-tight">@yield('heading')</h1>
                        <div class="text-pretty text-base/7 text-ink/65 sm:text-sm/6">@yield('description')</div>
                    </div>
                    @if (session('status'))
                        <div role="status" class="rounded-lg bg-lichen/60 p-4 text-base/6 text-moss sm:text-sm/6">{{ session('status') }}</div>
                    @endif
                    @yield('content')
                    <div class="border-t border-ink/10 pt-6 text-pretty text-base/7 text-ink/60 sm:text-sm/6">@yield('footer')</div>
                </section>
            </main>
            <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-ink/10 py-6 text-base text-ink/55 sm:text-sm">
                <p>© {{ date('Y') }} Sitewell</p>
                <a href="{{ route('marketing.home') }}" class="hover:text-garden">Back to Sitewell</a>
            </footer>
        </div>
    </div>
</body>
</html>
