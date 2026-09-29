<?php

namespace App\Http\Controllers;

use App\Models\Prospect;
use App\Services\ProspectUnsubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class ProspectUnsubscribeController extends Controller
{
    public function show(Request $request, Prospect $prospect): View
    {
        return view('prospects.unsubscribe', [
            'unsubscribed' => $prospect->unsubscribed_at !== null,
            'preview' => $request->query('preview') === '1',
            'confirmUrl' => URL::signedRoute('prospects.unsubscribe.store', $prospect),
        ]);
    }

    public function store(Request $request, Prospect $prospect, ProspectUnsubscriber $unsubscriber): RedirectResponse
    {
        abort_if($request->query('preview') === '1', 403);
        $unsubscriber->unsubscribe($prospect);

        return redirect()->to(URL::signedRoute('prospects.unsubscribe.show', $prospect));
    }
}
