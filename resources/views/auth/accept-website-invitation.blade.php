@extends('layouts.auth')
@section('title', 'Set up your account')
@section('eyebrow', 'You’re invited')
@section('heading', 'Make yourself at home')
@section('description')<p>Set up your account to access your website in Sitewell.</p>@endsection
@section('content')
    <div class="grid gap-1 border-l-2 border-garden pl-4 text-base sm:text-sm">
        <p class="text-ink/60">Your invitation is for</p>
        <p class="wrap-anywhere font-medium">{{ $user->email }}</p>
    </div>
    <form method="POST" action="{{ request()->fullUrl() }}" class="grid gap-5">
        @csrf
        @method('PUT')
        <x-auth.field name="name" label="Your name" :value="old('name', $user->name)" required autocomplete="name" autofocus />
        <x-auth.field name="password" label="Create a password" type="password" required autocomplete="new-password" hint="Use at least 8 characters." />
        <x-auth.field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
        <x-auth.submit>Set up account</x-auth.submit>
    </form>
@endsection
@section('footer')<p>Already set up your account? <a href="{{ route('login') }}" class="font-medium text-garden underline-offset-4 hover:underline">Sign in</a></p>@endsection
