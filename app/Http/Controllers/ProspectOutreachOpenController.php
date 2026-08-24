<?php

namespace App\Http\Controllers;

use App\Models\ProspectOutreachDelivery;
use App\Services\ProspectEngagementSourceClassifier;
use App\Services\ProspectOutreachTracker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProspectOutreachOpenController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, ProspectOutreachDelivery $delivery, ProspectOutreachTracker $tracker, ProspectEngagementSourceClassifier $sourceClassifier): Response
    {
        $tracker->recordOpen($delivery, $sourceClassifier->classify($request));

        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
