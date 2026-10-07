<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWebsiteAuditEngagementRequest;
use App\Models\WebsiteAudit;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class WebsiteAuditEngagementController extends Controller
{
    public function __invoke(StoreWebsiteAuditEngagementRequest $request, WebsiteAudit $websiteAudit): Response
    {
        $data = $request->validated();
        DB::transaction(function () use ($websiteAudit, $data): void {
            $websiteAudit->newQuery()->whereKey($websiteAudit->id)->lockForUpdate()->firstOrFail();
            $visit = $websiteAudit->visits()->firstOrCreate(['visit_id' => $data['visit_id']]);
            $elapsed = max(0, (int) $visit->created_at->diffInSeconds(now()) + 5);
            $visit->active_seconds = max($visit->active_seconds, min($data['active_seconds'], $elapsed));
            $visit->scroll_percent = max($visit->scroll_percent, $data['scroll_percent']);
            foreach (array_unique($data['events']) as $event) {
                if (! in_array($event, ['viewed', 'heartbeat'], true)) {
                    $column = $event.'_at';
                    $visit->{$column} ??= now();
                }
            }
            $visit->save();
        });

        return response()->noContent();
    }
}
