<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f1e8">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') · @yield('title') · Sitewell</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-paper font-sans text-ink">
    <div class="isolate flex min-h-dvh flex-col overflow-hidden">
        <header class="relative z-10">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-5 py-5 sm:px-8 lg:px-10">
                <a href="{{ url('/') }}" aria-label="Sitewell homepage" class="font-display text-2xl font-semibold tracking-tight text-ink">Sitewell</a>
                <a href="{{ url('/contact') }}" class="relative rounded-md px-3 py-2 text-base font-medium text-ink/70 ring-1 ring-ink/20 hover:bg-white/40 hover:text-ink sm:text-sm">Contact support<span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span></a>
            </div>
        </header>

        <main class="relative flex flex-1 items-center py-16 sm:py-24">
            <div class="pointer-events-none absolute -top-24 right-[-8rem] size-80 rounded-full border border-garden/15 sm:right-[-3rem] sm:size-112" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-44 left-[-7rem] size-96 rounded-full bg-lichen/70 sm:left-[-2rem] sm:size-128" aria-hidden="true"></div>

            <div class="relative mx-auto w-full max-w-7xl px-5 sm:px-8 lg:px-10">
                <div class="grid items-end gap-12 lg:grid-cols-[13fr_7fr] lg:gap-20">
                    <div class="flex flex-col gap-6">
                        <p class="font-mono text-base font-medium tracking-wide text-garden sm:text-sm">Error <span class="tabular-nums">@yield('code')</span></p>
                        <h1 class="max-w-[24ch] text-balance font-display text-5xl font-semibold tracking-tight text-ink sm:text-6xl lg:text-7xl">@yield('title')</h1>
                        <p class="max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">@yield('message')</p>
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <a href="{{ url('/') }}" class="relative rounded-md bg-garden px-4 py-3 text-base font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Return to homepage<span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span></a>
                            <a href="{{ url('/contact') }}" class="relative rounded-md px-4 py-3 text-base font-medium text-ink ring-1 ring-ink/20 hover:bg-white/50 sm:text-sm">Tell us what happened<span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span></a>
                        </div>
                    </div>

                    <div class="border-t border-ink/10 pt-6 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-10">
                        <p class="font-display text-8xl font-semibold tracking-tight text-garden/20 tabular-nums sm:text-9xl">@yield('code')</p>
                        <p class="max-w-[48ch] text-pretty text-base text-ink/55 sm:text-sm">If you were making a change, check its current status before trying again.</p>
                    </div>
                </div>
            </div>
        </main>

        <footer class="relative z-10 border-t border-ink/10">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-5 text-base text-ink/50 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:text-sm lg:px-10">
                <p>© {{ now()->year }} Sitewell.</p>
                <p>Your website, well looked after.</p>
            </div>
        </footer>
    </div>
</body>
</html>
