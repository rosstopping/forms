<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f1e8">
    <meta name="description" content="@yield('meta_description', 'Sitewell keeps your website healthy, visible, and ready to turn visitors into customers.')">
    <link rel="canonical" href="{{ request()->url() }}">
    <title>@yield('title', 'Sitewell') · Your website, well looked after</title>
    @yield('structured_data')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-paper font-sans text-ink">
    <div class="isolate min-h-dvh">
        <header class="border-b border-ink/10">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-5 py-5 sm:px-8 lg:px-10">
                <div class="flex flex-1 items-center">
                    <a href="{{ route('marketing.home') }}" aria-label="Homepage" class="font-display text-2xl font-semibold tracking-tight text-ink">Sitewell</a>
                </div>
                <nav class="hidden items-center gap-8 text-sm text-ink/65 lg:flex" aria-label="Main navigation">
                    @foreach ([['route' => 'marketing.how-it-works', 'label' => 'How it works'], ['route' => 'marketing.features', 'label' => 'Features'], ['route' => 'marketing.pricing', 'label' => 'Pricing'], ['route' => 'marketing.free-site-audit', 'label' => 'Free audit'], ['route' => 'marketing.journal', 'label' => 'Journal'], ['route' => 'marketing.contact', 'label' => 'Contact']] as $item)
                        <a href="{{ route($item['route']) }}" @class(['hover:text-ink', 'text-ink' => request()->routeIs($item['route'])])>{{ $item['label'] }}</a>
                    @endforeach
                </nav>
                <div class="hidden flex-1 items-center justify-end gap-5 text-sm lg:flex">
                    <a href="{{ route('login') }}" class="text-ink/65 hover:text-ink">Log in</a>
                    <a href="{{ route('marketing.contact') }}" class="rounded-md px-3 py-2 text-ink ring-1 ring-ink/20 hover:bg-white/40">Get started</a>
                </div>
                <details class="relative lg:hidden">
                    <summary class="relative list-none rounded-md px-3 py-2 text-base font-medium ring-1 ring-ink/20">Menu<span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span></summary>
                    <nav class="absolute right-0 z-50 mt-3 w-64 rounded-lg bg-[#fffefa] p-3 shadow-xl ring-1 ring-ink/10" aria-label="Mobile navigation">
                        <div class="grid gap-1">
                            <a href="{{ route('marketing.how-it-works') }}" class="rounded-md px-3 py-3 text-base hover:bg-lichen/50">How it works</a>
                            <a href="{{ route('marketing.features') }}" class="rounded-md px-3 py-3 text-base hover:bg-lichen/50">Features</a>
                            <a href="{{ route('marketing.pricing') }}" class="rounded-md px-3 py-3 text-base hover:bg-lichen/50">Pricing</a>
                            <a href="{{ route('marketing.free-site-audit') }}" class="rounded-md px-3 py-3 text-base hover:bg-lichen/50">Free site audit</a>
                            <a href="{{ route('marketing.journal') }}" class="rounded-md px-3 py-3 text-base hover:bg-lichen/50">Journal</a>
                            <a href="{{ route('marketing.contact') }}" class="rounded-md px-3 py-3 text-base hover:bg-lichen/50">Contact</a>
                            <a href="{{ route('login') }}" class="mt-2 border-t border-ink/10 px-3 pt-4 pb-3 text-base text-ink/65">Log in</a>
                        </div>
                    </nav>
                </details>
            </div>
        </header>

        <main>
            @yield('content')
        </main>

        <footer class="border-t border-ink/10 bg-ink text-paper">
            <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10">
                <div class="grid gap-10 md:grid-cols-[2fr_1fr_1fr]">
                    <div>
                        <a href="{{ route('marketing.home') }}" aria-label="Homepage" class="font-display text-2xl font-semibold tracking-tight">Sitewell</a>
                        <p class="mt-4 max-w-[42ch] text-pretty text-base text-paper/65 sm:text-sm">The calm care layer for the website your business depends on.</p>
                    </div>
                    <nav class="grid content-start gap-3 text-base text-paper/70 sm:text-sm" aria-label="Product">
                        <p class="font-medium text-paper">Product</p>
                        <a href="{{ route('marketing.how-it-works') }}" class="font-normal hover:text-paper">How it works</a>
                        <a href="{{ route('marketing.features') }}" class="font-normal hover:text-paper">Features</a>
                        <a href="{{ route('marketing.pricing') }}" class="font-normal hover:text-paper">Pricing</a>
                        <a href="{{ route('marketing.free-site-audit') }}" class="font-normal hover:text-paper">Free site audit</a>
                        <a href="{{ route('marketing.wordpress') }}" class="font-normal hover:text-paper">WordPress plugin</a>
                        <a href="{{ route('marketing.contact') }}" class="font-normal hover:text-paper">Get started</a>
                    </nav>
                    <nav class="grid content-start gap-3 text-base text-paper/70 sm:text-sm" aria-label="Company">
                        <p class="font-medium text-paper">Company</p>
                        <a href="{{ route('marketing.journal') }}" class="font-normal hover:text-paper">Journal</a>
                        <a href="{{ route('marketing.contact') }}" class="font-normal hover:text-paper">Contact</a>
                        <a href="{{ route('marketing.privacy') }}" class="font-normal hover:text-paper">Privacy policy</a>
                        <a href="{{ route('marketing.terms') }}" class="font-normal hover:text-paper">Terms of service</a>
                        <a href="{{ route('login') }}" class="font-normal hover:text-paper">Log in</a>
                    </nav>
                </div>
                <p class="mt-12 border-t border-paper/10 pt-6 text-base text-paper/50 sm:text-sm">© {{ now()->year }} Sitewell. Your website, well looked after.</p>
            </div>
        </footer>
    </div>
</body>
</html>
