@extends('layouts.auth')
@section('title', 'Reset your password')
@section('eyebrow', 'Let’s get you back in')
@section('heading', 'Forgot your password?')
@section('description')<p>Enter the email address you use for Sitewell and we’ll send you a password reset link.</p>@endsection
@section('content')
    <form method="POST" action="{{ route('password.email') }}" class="grid gap-5">
        @csrf
        <x-auth.field name="email" label="Email address" type="email" :value="old('email')" required autocomplete="email" autofocus />
        <x-auth.submit>Send reset link</x-auth.submit>
    </form>
@endsection
@section('footer')<p>Remembered it? <a href="{{ route('login') }}" class="font-medium text-garden underline-offset-4 hover:underline">Back to sign in</a></p>@endsection
