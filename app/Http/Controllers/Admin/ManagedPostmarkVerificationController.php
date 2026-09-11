<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\ManagedPostmarkProvisioner;
use Illuminate\Http\RedirectResponse;
use Throwable;

class ManagedPostmarkVerificationController extends Controller
{
    public function __invoke(Website $website, ManagedPostmarkProvisioner $provisioner): RedirectResponse
    {
        abort_unless($website->isManageableBy(request()->user()), 403);
        $connection = $website->mailConnection;
        abort_unless($connection, 404);

        try {
            $connection = $provisioner->refresh($connection);
        } catch (Throwable) {
            return back()->withErrors(['sending_domain' => 'Postmark verification could not be checked. Please try again.']);
        }

        return back()->with('status', $connection->dkim_verified
            ? 'The sending domain is verified and ready to use.'
            : 'The DKIM record is not verified yet. DNS changes can take time to appear.');
    }
}
