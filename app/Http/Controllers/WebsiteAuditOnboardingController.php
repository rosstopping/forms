<?php

namespace App\Http\Controllers;

use App\Actions\ActivateWebsiteAuditTrial;
use App\Http\Requests\ClaimWebsiteAuditRequest;
use App\Http\Requests\CompleteWebsiteAuditOnboardingRequest;
use App\Models\User;
use App\Models\WebsiteAudit;
use App\Notifications\WebsiteAuditClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class WebsiteAuditOnboardingController extends Controller
{
    public function store(ClaimWebsiteAuditRequest $request, WebsiteAudit $websiteAudit): RedirectResponse
    {
        $websiteAudit->update([
            'email' => $request->validated('email'),
            'claim_email_sent_at' => now(),
        ]);

        Notification::route('mail', $websiteAudit->email)
            ->notify((new WebsiteAuditClaim($websiteAudit))->afterCommit());

        return back()->with('claim_status', 'Check your inbox for a secure link to continue.');
    }

    public function edit(Request $request, WebsiteAudit $websiteAudit, ActivateWebsiteAuditTrial $activate): View|RedirectResponse
    {
        abort_if($websiteAudit->hasExpired() || blank($websiteAudit->email), 410);

        $existingUser = User::query()->where('email', $websiteAudit->email)->first();

        if ($existingUser) {
            $user = $activate->handle($websiteAudit);

            return $this->authenticate($request, $user, $websiteAudit);
        }

        return view('auth.complete-website-audit-onboarding', ['audit' => $websiteAudit]);
    }

    public function update(
        CompleteWebsiteAuditOnboardingRequest $request,
        WebsiteAudit $websiteAudit,
        ActivateWebsiteAuditTrial $activate,
    ): RedirectResponse {
        $user = $activate->handle($websiteAudit, $request->validated());

        return $this->authenticate($request, $user, $websiteAudit);
    }

    private function authenticate(Request $request, User $user, WebsiteAudit $audit): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.websites.show', $audit->refresh()->website)
            ->with('status', 'Your 14-day Sitewell trial has started.');
    }
}
