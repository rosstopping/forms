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
    @vite(['resources/css/app.css'])
</head>
<body class="prospect-workspace bg-white text-slate-950">
@include('website-growth-plans.content')
</body>
</html>
