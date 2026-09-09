@extends('layouts.auth')
@section('title', 'Sign in')
@section('eyebrow', 'Welcome back')
@section('heading', 'Good to see you again')
@section('description')<p>Sign in to see how your website is doing and pick up where you left off.</p>@endsection
@section('content')
    <form method="POST" action="{{ route('login') }}" class="grid gap-5">
        @csrf
        <x-auth.field name="email" label="Email address" type="email" :value="old('email')" required autocomplete="username" autofocus />
        <x-auth.field name="password" label="Password" type="password" required autocomplete="current-password" />
        <p class="text-right text-base sm:text-sm"><a href="{{ route('password.request') }}" class="font-medium text-garden underline-offset-4 hover:underline">Forgot your password?</a></p>
        <x-auth.submit>Sign in</x-auth.submit>
    </form>
@endsection
@section('footer')<p>New to Sitewell? <a href="{{ route('marketing.free-site-audit') }}" class="font-medium text-garden underline-offset-4 hover:underline">Get started</a></p>@endsection
