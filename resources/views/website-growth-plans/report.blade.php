<!DOCTYPE html>
<html lang="en-GB" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $plan->report['website_name'] }} · Website plan of action</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" sizes="180x180">
    <meta name="theme-color" content="#faf7f4">
    <style>
        @page { size: A4; margin: 12mm; }
    </style>
    @vite(['resources/css/app.css'])
</head>
<body class="marketing-site prospect-workspace bg-white font-sans text-ink [print-color-adjust:exact] [-webkit-print-color-adjust:exact]">
@include('website-growth-plans.content')
</body>
</html>
