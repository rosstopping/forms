<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;

class PixelKeyController extends Controller
{
    public function __invoke(Request $request, Website $website): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);

        do {
            $publicKey = 'sw_'.Str::lower(Str::random(28));
        } while (Website::query()->where('pixel_public_key', $publicKey)->exists());

        $website->forceFill([
            'pixel_public_key' => $publicKey,
            'pixel_payload_version' => $website->pixel_payload_version + 1,
            'pixel_last_seen_at' => null,
            'pixel_last_seen_url' => null,
            'pixel_last_seen_hostname' => null,
            'pixel_version' => null,
        ])->save();
        Log::warning('Website Pixel public key rotated.', [
            'website_id' => $website->id,
            'performed_by' => $request->user()?->id,
        ]);

        return Redirect::route('admin.websites.show', ['website' => $website, 'tab' => 'pixel'])
            ->with('status', 'Pixel key rotated. Replace the installation snippet on the website.');
    }
}
