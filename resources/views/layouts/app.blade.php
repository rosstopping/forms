<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-slate-50 text-slate-900">
    <div class="isolate mx-auto max-w-7xl p-4 sm:p-6">
        @auth
            <nav class="mb-6 flex flex-wrap items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm" aria-label="Main navigation">
                <a href="{{ route('admin.dashboard') }}" aria-label="Homepage" class="mr-2 rounded-md px-2 py-2 text-sm font-semibold text-slate-950 hover:bg-slate-100">Site health</a>
                <a href="{{ route('admin.websites.index') }}" @class(['rounded-md px-3 py-2 text-sm font-medium transition-colors', 'bg-slate-900 text-white' => request()->routeIs('admin.websites.*'), 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' => ! request()->routeIs('admin.websites.*')])>Websites</a>
                @if (Auth::user()?->isAdmin())
                    <a href="{{ route('admin.users.index') }}" @class(['rounded-md px-3 py-2 text-sm font-medium transition-colors', 'bg-slate-900 text-white' => request()->routeIs('admin.users.*'), 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' => ! request()->routeIs('admin.users.*')])>Users</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                    @csrf
                    <button type="submit" class="rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Logout</button>
                </form>
            </nav>
        @endauth

        @yield('content')
    </div>
</body>
</html>
