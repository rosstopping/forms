<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProspectRequest;
use App\Http\Requests\UpdateProspectRequest;
use App\Jobs\AnalyzeProspect;
use App\Models\Prospect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProspectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = Prospect::query()->accessibleTo($request->user());
        $summary = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $prospects = $query->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query->where('business_name', 'like', '%'.$request->string('search').'%')->orWhere('email', 'like', '%'.$request->string('search').'%')))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.prospects.index', compact('prospects', 'summary'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.prospects.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProspectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['website_url'] = rtrim($data['website_url'], '/');
        $prospect = $request->user()->prospects()->create($data);
        $prospect->recordActivity('created', 'Prospect added and queued for research.', $request->user());
        AnalyzeProspect::dispatch($prospect);

        return redirect()->route('admin.prospects.show', $prospect)->with('status', 'Prospect added. Website research has been queued.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Prospect $prospect): View
    {
        abort_unless($prospect->isAccessibleBy($request->user()), 403);

        return view('admin.prospects.show', ['prospect' => $prospect->load('activities.user')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Prospect $prospect)
    {
        return redirect()->route('admin.prospects.show', $prospect);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProspectRequest $request, Prospect $prospect): RedirectResponse
    {
        $data = $request->validated();
        $data['suppressed_at'] = $request->boolean('suppressed') ? ($prospect->suppressed_at ?: now()) : null;
        unset($data['suppressed']);
        $draftChanged = $prospect->outreach_subject !== ($data['outreach_subject'] ?? null) || $prospect->outreach_body !== ($data['outreach_body'] ?? null);
        if ($draftChanged) {
            $data['approved_at'] = null;
            $data['approved_by'] = null;
            $data['status'] = 'drafted';
        }
        $prospect->update($data);
        $prospect->recordActivity('updated', $draftChanged ? 'Prospect and outreach draft updated; approval reset.' : 'Prospect updated.', $request->user());

        return back()->with('status', 'Prospect updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Prospect $prospect): RedirectResponse
    {
        abort_unless($prospect->isAccessibleBy($request->user()), 403);
        $prospect->delete();

        return redirect()->route('admin.prospects.index')->with('status', 'Prospect deleted.');
    }
}
