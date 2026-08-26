<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProspectingLocationRequest;
use App\Http\Requests\UpdateProspectingLocationRequest;
use App\Models\ProspectingLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProspectingLocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function create(): View
    {
        return view('admin.prospecting-strategy.location-form', ['location' => new ProspectingLocation]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProspectingLocationRequest $request): RedirectResponse
    {
        ProspectingLocation::query()->create($request->validated());

        return redirect()->route('admin.prospecting-industry-profiles.index')->with('status', 'Prospecting location created.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProspectingLocation $prospectingLocation): View
    {
        return view('admin.prospecting-strategy.location-form', ['location' => $prospectingLocation]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProspectingLocationRequest $request, ProspectingLocation $prospectingLocation): RedirectResponse
    {
        $prospectingLocation->update($request->validated());

        return redirect()->route('admin.prospecting-industry-profiles.index')->with('status', 'Prospecting location updated.');
    }
}
