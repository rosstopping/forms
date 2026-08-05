<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50">
    <div class="mx-auto flex min-h-screen max-w-md items-center justify-center p-6">
        <div class="w-full rounded-lg border bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-semibold">Admin login</h1>
            <p class="mt-2 text-sm text-slate-600">Sign in to manage websites, forms, and submissions.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium" for="email">Email</label>
                    <input id="email" name="email" type="email" required class="w-full rounded border px-3 py-2">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="password">Password</label>
                    <input id="password" name="password" type="password" required class="w-full rounded border px-3 py-2">
                </div>
                <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white">Sign in</button>
            </form>
        </div>
    </div>
</body>
</html>
