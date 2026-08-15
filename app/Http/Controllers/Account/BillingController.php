<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\StripeBillingClient;
use App\Support\MembershipPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.billing', [
            'plans' => MembershipPlan::all(),
            'user' => $request->user(),
        ]);
    }

    public function checkout(Request $request, StripeBillingClient $stripe): RedirectResponse
    {
        $data = $request->validate([
            'tier' => ['required', 'string', Rule::in(array_keys(MembershipPlan::all()))],
        ]);
        $user = $request->user();

        if ($user->hasActiveMembership() && $user->stripe_customer_id) {
            return $this->portal($request, $stripe);
        }

        $plan = MembershipPlan::find($data['tier']);
        $priceId = (string) ($plan['stripe_price_id'] ?? '');

        if ($priceId === '') {
            return Redirect::back()->with('error', 'The Stripe price for this package has not been configured.');
        }

        $session = $stripe->createCheckoutSession(
            $user,
            $priceId,
            route('admin.billing.index', ['checkout' => 'success']),
            route('admin.billing.index', ['checkout' => 'cancelled']),
        );

        return Redirect::away($session['url']);
    }

    public function portal(Request $request, StripeBillingClient $stripe): RedirectResponse
    {
        $session = $stripe->createPortalSession($request->user(), route('admin.billing.index'));

        return Redirect::away($session['url']);
    }
}
