<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RunAutomatedProspectDiscoveryRequest;
use App\Services\AutomatedProspectDiscovery;
use Illuminate\Http\RedirectResponse;

class RunAutomatedProspectDiscoveryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(RunAutomatedProspectDiscoveryRequest $request, AutomatedProspectDiscovery $discovery): RedirectResponse
    {
        $result = $discovery->dispatch($request->user(), $request->integer('limit'));
        $message = match (true) {
            $result['enabled_industries'] === 0 => 'Automatic prospecting needs at least one enabled industry. Add or enable one in Automation strategy.',
            $result['enabled_locations'] === 0 => 'Automatic prospecting needs at least one enabled location. Add or enable one in Automation strategy.',
            $result['searches'] === 0 => 'No new industry and location combinations are due this month.',
            default => sprintf('Queued %d automatic searches covering %d keyword operations. Estimated provider cost: $%.4f.', $result['searches'], $result['operations'], $result['estimated_cost']),
        };

        return redirect()->to(route('admin.prospect-discoveries.index').'#automatic-prospecting')->with('status', $message);
    }
}
