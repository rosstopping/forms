<?php

namespace App\Http\Controllers;

use App\Models\ProspectOutreachDelivery;
use App\Services\ProspectOutreachTracker;
use Illuminate\Http\Response;

class ProspectOutreachOpenController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ProspectOutreachDelivery $delivery, ProspectOutreachTracker $tracker): Response
    {
        $tracker->recordOpen($delivery);

        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
