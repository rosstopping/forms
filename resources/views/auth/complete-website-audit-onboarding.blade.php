@extends('layouts.auth')
@section('title', 'Complete your Sitewell profile')
@section('eyebrow', 'One last step')
@section('heading', 'Complete your profile')
@section('description')<p>Your email is confirmed. Set up your profile to begin the 14-day Growth trial for <span class="wrap-anywhere font-medium text-ink">{{ $audit->domain }}</span>, including SEO performance features.</p>@endsection
@section('content')
    <form method="POST" action="{{ request()->fullUrl() }}" class="grid gap-5">
        @csrf
        <x-auth.field name="name" label="Your name" :value="old('name')" required autocomplete="name" autofocus />
        <x-auth.field name="password" label="Create a password" type="password" required autocomplete="new-password" hint="Use at least 8 characters." />
        <x-auth.field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
        <x-auth.submit>Start my 14-day Growth trial</x-auth.submit>
    </form>
@endsection
@section('footer')<p>No card needed. Your trial starts when you complete your profile.</p>@endsection
