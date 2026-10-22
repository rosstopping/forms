<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckPhpSecurityRequest;
use App\Services\PhpSecurityChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;

class PhpSecurityCheckController extends Controller
{
    public function create(): View
    {
        return view('marketing.php-security-check', ['result' => null]);
    }

    public function store(CheckPhpSecurityRequest $request, PhpSecurityChecker $checker): View
    {
        $url = $request->validated('url');
        $key = 'php-security-domain:'.hash('sha256', strtolower(parse_url($url, PHP_URL_HOST) ?? ''));
        abort_unless(RateLimiter::attempt($key, 10, fn (): bool => true, 60), 429);

        return view('marketing.php-security-check', ['result' => $checker->check($url)]);
    }
}
