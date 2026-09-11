<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManagedPostmarkConnectionRequest;
use App\Models\Website;
use App\Services\ManagedPostmarkProvisioner;
use Illuminate\Http\RedirectResponse;
use Throwable;

class ManagedPostmarkConnectionController extends Controller
{
    public function __invoke(StoreManagedPostmarkConnectionRequest $request, Website $website, ManagedPostmarkProvisioner $provisioner): RedirectResponse
    {
        try {
            $provisioner->provision($website, $request->validated('sending_domain'));
        } catch (Throwable) {
            return back()->withErrors(['sending_domain' => 'Postmark could not create the managed sending domain. Please try again.']);
        }

        return back()->with('status', 'Managed Postmark created. Add the DNS records shown below, then check verification.');
    }
}
