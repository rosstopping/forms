<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWebsiteAutoresponderRequest;
use App\Models\Website;
use App\Services\AutoresponderHtmlSanitizer;
use Illuminate\Http\RedirectResponse;

class WebsiteAutoresponderController extends Controller
{
    public function __construct(private AutoresponderHtmlSanitizer $autoresponderHtmlSanitizer) {}

    public function __invoke(UpdateWebsiteAutoresponderRequest $request, Website $website): RedirectResponse
    {
        $settings = $request->validated();
        $settings['autoresponder_body'] = $this->autoresponderHtmlSanitizer->sanitize($settings['autoresponder_body'] ?? null);

        $website->update($settings);

        return redirect()->route('admin.websites.show', $website)->with('status', 'Automatic reply settings updated.');
    }
}
