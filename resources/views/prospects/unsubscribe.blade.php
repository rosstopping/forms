<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Unsubscribe from Digizu outreach</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-slate-50 text-slate-900">
    <main class="mx-auto max-w-lg px-5 py-16 sm:py-24">
        <section class="ui-panel ui-section space-y-4">
            <p class="text-sm font-medium text-slate-500">Digizu</p>
            @if ($preview)
                <h1 class="text-2xl font-semibold">Unsubscribe preview</h1>
                <p class="text-base text-slate-600">This is a test email link. No outreach preferences have been changed. Live emails let the recipient confirm their unsubscribe here.</p>
            @elseif ($unsubscribed)
                <h1 class="text-2xl font-semibold">You’re unsubscribed</h1>
                <p class="text-base text-slate-600">You won’t receive any more outreach emails from us. Your pending emails and follow-ups have been cancelled.</p>
            @else
                <h1 class="text-2xl font-semibold">Unsubscribe</h1>
                <p class="text-base text-slate-600">Confirm below to stop receiving outreach emails from Digizu, including follow-ups and personalised videos.</p>
                <form method="POST" action="{{ $confirmUrl }}">
                    @csrf
                    <button type="submit" class="ui-button ui-button-primary">Confirm unsubscribe</button>
                </form>
            @endif
        </section>
    </main>
</body>
</html>
