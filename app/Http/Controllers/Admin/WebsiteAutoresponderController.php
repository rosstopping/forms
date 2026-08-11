<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWebsiteAutoresponderRequest;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;

class WebsiteAutoresponderController extends Controller
{
    public function __invoke(UpdateWebsiteAutoresponderRequest $request, Website $website): RedirectResponse
    {
        $website->update($request->validated());

        return redirect()->route('admin.websites.show', $website)->with('status', 'Automatic reply settings updated.');
    }
}
