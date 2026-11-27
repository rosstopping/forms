<header class="border-b border-slate-200 bg-white">
    <nav aria-label="Reporting navigation" class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-5 gap-y-2 px-4 py-4 sm:px-8">
        <a href="{{ route('admin.reporting.index') }}" class="mr-auto flex items-center gap-2 font-semibold text-slate-950"><img src="{{ asset('favicon.svg') }}" alt="" width="28" height="28"> Sitewell</a>
        <a href="{{ route('admin.reporting.index') }}" class="text-sm font-medium text-teal-800">All websites</a>
        <a href="{{ route('admin.profile.edit') }}" class="text-sm text-slate-700">Profile</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-sm text-slate-700" type="submit">Log out</button></form>
    </nav>
</header>
<main class="admin-content mx-auto max-w-7xl space-y-8 px-4 py-7 sm:px-8 sm:py-10">@yield('content')</main>
