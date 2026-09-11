<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendPasswordResetLinkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(SendPasswordResetLinkRequest $request): RedirectResponse
    {
        Password::sendResetLink($request->validated());

        return back()->withInput($request->only('email'))->with('status', 'If an account matches that email address, a password reset link will arrive shortly. Please check your inbox and spam folder.');
    }
}
