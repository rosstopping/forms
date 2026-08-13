<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePixelSettingsRequest;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class PixelSettingsController extends Controller
{
    public function update(UpdatePixelSettingsRequest $request, Website $website): RedirectResponse
    {
        $enabled = $request->boolean('pixel_enabled');

        if ($website->pixel_enabled !== $enabled) {
            $website->forceFill([
                'pixel_enabled' => $enabled,
                'pixel_payload_version' => $website->pixel_payload_version + 1,
            ])->save();
            Log::notice('Website Pixel availability changed.', [
                'website_id' => $website->id,
                'enabled' => $enabled,
                'performed_by' => $request->user()?->id,
            ]);
        }

        return Redirect::route('admin.websites.show', ['website' => $website, 'tab' => 'pixel'])
            ->with('status', $enabled ? 'Sitewell Pixel enabled.' : 'All Pixel optimisations disabled for this website.');
    }
}
