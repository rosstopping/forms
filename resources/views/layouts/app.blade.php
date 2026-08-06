<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="mx-auto max-w-7xl p-6">
        @auth
            <nav class="mb-6 flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <a href="{{ route('admin.dashboard') }}" class="rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Dashboard</a>
                <a href="{{ route('admin.websites.index') }}" class="rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Websites</a>
                <a href="{{ route('admin.forms.index') }}" class="rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Forms</a>
                <a href="{{ route('admin.form-submissions.index') }}" class="rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Submissions</a>
                @if (Auth::user()?->isAdmin())
                    <a href="{{ route('admin.users.index') }}" class="rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Users</a>
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
