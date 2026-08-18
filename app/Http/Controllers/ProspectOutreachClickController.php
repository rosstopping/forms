<?php

namespace App\Http\Controllers;

use App\Models\ProspectOutreachLink;
use App\Services\ProspectOutreachTracker;
use Illuminate\Http\RedirectResponse;

class ProspectOutreachClickController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ProspectOutreachLink $link, ProspectOutreachTracker $tracker): RedirectResponse
    {
        $tracker->recordClick($link);

        return redirect()->away($link->destination_url);
    }
}
