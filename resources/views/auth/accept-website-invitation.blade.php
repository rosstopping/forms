<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set up your Sitewell account</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50">
    <main class="mx-auto flex min-h-screen max-w-md items-center justify-center p-6">
        <section class="w-full rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
            <p class="text-sm font-medium text-teal-700">Website invitation</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950">Set up your account</h1>
            <p class="mt-2 text-sm text-slate-600">Create your sign-in details for <span class="font-medium text-slate-900">{{ $user->email }}</span>.</p>

            <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
                    <input id="name" name="name" type="text" required autocomplete="name" value="{{ old('name', $user->name) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('password')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Set up account</button>
            </form>
        </section>
    </main>
</body>
</html>
