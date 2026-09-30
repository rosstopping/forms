<!DOCTYPE html>
<html lang="en-GB" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f1e8">
    <title>{{ $landing['title'] }}</title>
    <meta name="description" content="{{ $landing['description'] }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:site_name" content="Sitewell">
    <meta property="og:locale" content="en_GB">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $landing['title'] }}">
    <meta property="og:description" content="{{ $landing['description'] }}">
    <meta property="og:url" content="{{ $canonical }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/marketing-events.js'])
    @yield('structured_data')
</head>
<body class="marketing-site prospect-workspace bg-white text-ink">
    <div class="isolate min-h-dvh">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4">Skip to content</a>
        <header class="border-b border-ink/10">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-5 px-5 py-4 sm:px-8 lg:px-10">
                <p class="font-sans text-2xl font-semibold tracking-tight"><a href="{{ route('marketing.home') }}" aria-label="Homepage">sitewell<span class="text-garden">.</span></a></p>
                <nav aria-label="Landing page navigation" class="flex items-center gap-6 text-base sm:text-sm">
                    <a href="#ross" class="hover:text-garden max-sm:hidden">Meet Ross</a>
                    <a href="{{ route('marketing.ppc.book') }}" class="inline-flex min-h-12 items-center underline decoration-ink/30 underline-offset-4 hover:decoration-ink">Book a call <span class="pl-2" aria-hidden="true">↗</span></a>
                </nav>
            </div>
        </header>
        <main id="main" data-ppc-page="{{ $pageKey }}" data-marketing-attribution="{{ json_encode($attribution) }}">
            @yield('content')
        </main>
        <footer class="border-t border-ink/15 py-8">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-6 px-5 text-base text-ink/70 sm:px-8 sm:text-sm lg:px-10">
                <p>© {{ now()->year }} Sitewell. Your website, well looked after.</p>
                <nav aria-label="Company information" class="flex flex-wrap gap-x-6 gap-y-3">
                    <a class="font-normal hover:text-ink" href="{{ route('marketing.about') }}">About</a>
                    <a class="font-normal hover:text-ink" href="{{ route('marketing.contact') }}">Contact</a>
                    <a class="font-normal hover:text-ink" href="{{ route('marketing.privacy') }}">Privacy</a>
                    <a class="font-normal hover:text-ink" href="{{ route('marketing.terms') }}">Terms</a>
                    <a class="font-normal hover:text-ink" href="{{ route('login') }}">Log in</a>
                </nav>
            </div>
        </footer>
    </div>
</body>
</html>
