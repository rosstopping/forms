<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWebsiteServiceRequest;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class WebsiteServiceController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UpdateWebsiteServiceRequest $request, Website $website): RedirectResponse
    {
        $data = $request->validated();
        $website->update([
            'service_package' => $data['service_package'],
            'service_status' => $data['service_status'],
            'service_ends_at' => filled($data['service_ends_on'] ?? null)
                ? Carbon::parse($data['service_ends_on'])->endOfDay()
                : null,
        ]);

        return redirect()->route('admin.websites.section', [$website, 'settings'])
            ->with('status', 'Website service settings saved.');
    }
}
