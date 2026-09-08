<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Added to the content queue · Sitewell</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-paper font-sans text-ink antialiased">
    <main class="mx-auto flex min-h-dvh max-w-xl items-center justify-center p-6">
        <div class="flex w-full flex-col gap-6 rounded-xl border border-ink/10 bg-white p-6 shadow-sm sm:p-10">
            <p class="font-display text-2xl font-semibold tracking-tight">Sitewell</p>
            <div class="flex size-12 items-center justify-center rounded-full bg-lichen text-garden" aria-hidden="true">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m5 12 4 4L19 6" />
                </svg>
            </div>
            <div class="flex flex-col gap-3">
                <h1 class="font-display text-3xl font-semibold tracking-tight">Added to the content queue</h1>
                <p class="text-base leading-7 text-ink/70">The content idea for <span class="font-medium break-words text-ink">{{ $website->name }}</span> has been added to the next content run.</p>
            </div>
            <p class="border-t border-ink/10 pt-6 text-sm text-ink/60">You can close this window.</p>
        </div>
    </main>
</body>
</html>
