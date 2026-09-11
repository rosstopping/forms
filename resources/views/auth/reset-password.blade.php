@extends('layouts.auth')
@section('title', 'Choose a new password')
@section('eyebrow', 'A fresh start')
@section('heading', 'Choose a new password')
@section('description')<p>Set a new password for your account. You’ll use it the next time you sign in.</p>@endsection
@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="grid gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @error('token')<p role="alert" class="text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror
        <x-auth.field name="email" label="Email address" type="email" :value="old('email', request()->query('email'))" required autocomplete="username" />
        <x-auth.field name="password" label="New password" type="password" required autocomplete="new-password" hint="Use at least 8 characters." autofocus />
        <x-auth.field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
        <x-auth.submit>Reset password</x-auth.submit>
    </form>
@endsection
@section('footer')<p>Link expired? <a href="{{ route('password.request') }}" class="font-medium text-garden underline-offset-4 hover:underline">Request a new link</a></p>@endsection
