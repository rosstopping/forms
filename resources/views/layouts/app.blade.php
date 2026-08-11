<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-[#f5f7f7] text-slate-900">
    @auth
        <div class="isolate min-h-dvh lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]">
            <aside class="hidden min-h-dvh flex-col bg-slate-950 px-4 py-5 text-white lg:flex">
                <a href="{{ route('admin.dashboard') }}" aria-label="Homepage" class="flex items-center gap-3 px-2">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-teal-400 text-sm font-semibold text-slate-950">S</span>
                    <span class="min-w-0">
                        <span class="flex text-sm font-semibold tracking-tight">Sitewell</span>
                        <span class="flex text-xs text-slate-400">Audit workspace</span>
                    </span>
                </a>

                <nav class="mt-10" aria-label="Main navigation">
                    <p class="px-3 text-xs font-medium uppercase tracking-widest text-slate-500">Workspace</p>
                    <div class="mt-2 space-y-1">
                        <a href="{{ route('admin.dashboard') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium', 'bg-white/10 text-white' => request()->routeIs('admin.dashboard'), 'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('admin.dashboard')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-5 shrink-0" aria-hidden="true"><path d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z" stroke-linejoin="round"/></svg>
                            Overview
                        </a>
                        <a href="{{ route('admin.websites.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium', 'bg-white/10 text-white' => request()->routeIs(['admin.websites.*', 'admin.website-health-reports.*', 'admin.website-repositories.*', 'admin.search-console.*', 'admin.forms.*']), 'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs(['admin.websites.*', 'admin.website-health-reports.*', 'admin.website-repositories.*', 'admin.search-console.*', 'admin.forms.*'])])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-5 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                            Websites
                        </a>
                        <a href="{{ route('admin.form-submissions.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium', 'bg-white/10 text-white' => request()->routeIs('admin.form-submissions.*'), 'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('admin.form-submissions.*')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-5 shrink-0" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v13a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 18.5v-13Z"/><path d="M8 8h8M8 12h8M8 16h5" stroke-linecap="round"/></svg>
                            <span class="flex-1">Leads</span>
                            @if ($newLeadCount > 0)
                                <span data-new-leads-count class="min-w-6 rounded-full bg-teal-400 px-1.5 py-0.5 text-center text-xs font-semibold tabular-nums text-slate-950" aria-label="{{ $newLeadCount }} new leads">{{ $newLeadCount > 99 ? '99+' : $newLeadCount }}</span>
                            @endif
                            @if ($followUpReminderCount > 0)
                                <span class="min-w-6 rounded-full bg-amber-300 px-1.5 py-0.5 text-center text-xs font-semibold tabular-nums text-slate-950" aria-label="{{ $followUpReminderCount }} lead follow-ups due" title="Follow-ups due">{{ $followUpReminderCount > 99 ? '99+' : $followUpReminderCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.prospects.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium', 'bg-white/10 text-white' => request()->routeIs('admin.prospects.*'), 'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('admin.prospects.*')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-5 shrink-0" aria-hidden="true"><path d="M4 19V9m6 10V5m6 14v-7m4 7H2" stroke-linecap="round"/><path d="m4 7 6-4 6 7 4-3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Outreach
                        </a>
                    </div>

                    @if (Auth::user()?->isAdmin())
                        <p class="mt-8 px-3 text-xs font-medium uppercase tracking-widest text-slate-500">Administration</p>
                        <div class="mt-2">
                            <a href="{{ route('admin.users.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium', 'bg-white/10 text-white' => request()->routeIs('admin.users.*'), 'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('admin.users.*')])>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-5 shrink-0" aria-hidden="true"><path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-4A4.5 4.5 0 0 0 3 18.5V20M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM17 11a3 3 0 0 0 0-6M16 14a4 4 0 0 1 5 4v2" stroke-linecap="round"/></svg>
                                Users
                            </a>
                        </div>
                    @endif
                </nav>

                <div class="mt-auto border-t border-white/10 pt-4">
                    <div class="flex items-center gap-3 px-2">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-teal-400/15 text-sm font-semibold text-teal-300">{{ Str::upper(Str::substr(Auth::user()->name, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-white">{{ Auth::user()->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ Auth::user()->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="relative rounded-md px-2 py-2 text-xs font-medium text-slate-400 hover:bg-white/5 hover:text-white" aria-label="Log out">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-5 shrink-0" aria-hidden="true"><path d="M14 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-3M10 12h11m-3-3 3 3-3 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 lg:hidden" aria-hidden="true"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            <div class="min-w-0">
                <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-950/10 bg-white/90 px-4 backdrop-blur lg:hidden">
                    <a href="{{ route('admin.dashboard') }}" aria-label="Homepage" class="flex items-center gap-2 text-sm font-semibold tracking-tight text-slate-950"><span class="grid size-8 place-items-center rounded-lg bg-slate-950 text-xs text-teal-300">S</span> Sitewell</a>
                    <button type="button" class="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100" aria-label="Open navigation" aria-expanded="false" aria-controls="mobile-navigation" data-mobile-nav-toggle>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-6" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/></svg>
                        <span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
                    </button>
                </header>

                <div id="mobile-navigation" class="fixed inset-0 z-40 hidden lg:hidden" data-mobile-nav>
                    <button type="button" class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" aria-label="Close navigation" data-mobile-nav-close></button>
                    <div class="absolute inset-y-0 right-0 flex w-[min(22rem,88vw)] flex-col bg-slate-950 p-5 text-white shadow-2xl">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold">Navigation</p>
                            <button type="button" class="relative rounded-lg p-2 text-slate-400 hover:bg-white/5 hover:text-white" aria-label="Close navigation" data-mobile-nav-close><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-6" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg><span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span></button>
                        </div>
                        <nav class="mt-8 space-y-2" aria-label="Mobile navigation">
                            <a href="{{ route('admin.dashboard') }}" class="flex rounded-lg px-3 py-3 text-base font-medium text-slate-200 hover:bg-white/5">Overview</a>
                            <a href="{{ route('admin.websites.index') }}" class="flex rounded-lg px-3 py-3 text-base font-medium text-slate-200 hover:bg-white/5">Websites</a>
                            <a href="{{ route('admin.form-submissions.index') }}" class="flex items-center justify-between gap-3 rounded-lg px-3 py-3 text-base font-medium text-slate-200 hover:bg-white/5"><span>Leads</span><span class="flex gap-1">@if ($newLeadCount > 0)<span data-new-leads-count class="min-w-7 rounded-full bg-teal-400 px-2 py-0.5 text-center text-xs font-semibold tabular-nums text-slate-950" aria-label="{{ $newLeadCount }} new leads">{{ $newLeadCount > 99 ? '99+' : $newLeadCount }}</span>@endif @if ($followUpReminderCount > 0)<span class="min-w-7 rounded-full bg-amber-300 px-2 py-0.5 text-center text-xs font-semibold tabular-nums text-slate-950" aria-label="{{ $followUpReminderCount }} lead follow-ups due">{{ $followUpReminderCount > 99 ? '99+' : $followUpReminderCount }}</span>@endif</span></a>
                            <a href="{{ route('admin.prospects.index') }}" class="flex rounded-lg px-3 py-3 text-base font-medium text-slate-200 hover:bg-white/5">Outreach</a>
                            @if (Auth::user()?->isAdmin())<a href="{{ route('admin.users.index') }}" class="flex rounded-lg px-3 py-3 text-base font-medium text-slate-200 hover:bg-white/5">Users</a>@endif
                        </nav>
                        <form method="POST" action="{{ route('logout') }}" class="mt-auto border-t border-white/10 pt-4">
                            @csrf
                            <button type="submit" class="w-full rounded-lg px-3 py-3 text-left text-base font-medium text-slate-300 hover:bg-white/5 hover:text-white">Log out</button>
                        </form>
                    </div>
                </div>

                <main class="admin-content mx-auto max-w-[96rem] p-4 sm:p-6 lg:p-8 xl:p-10">
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        <main class="isolate mx-auto min-h-dvh max-w-7xl p-4 sm:p-6">
            @yield('content')
        </main>
    @endauth
</body>
</html>
