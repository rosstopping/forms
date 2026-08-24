<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkProspectActionRequest;
use App\Jobs\AnalyzeProspect;
use App\Jobs\SendScheduledProspectOutreach;
use App\Models\Prospect;
use App\Services\ProspectLifecycleManager;
use App\Services\ProspectOutreachSender;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Throwable;

class BulkProspectActionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(BulkProspectActionRequest $request, ProspectOutreachSender $sender, ProspectLifecycleManager $lifecycleManager): RedirectResponse
    {
        $data = $request->validated();
        $prospects = $this->selectedProspects($request, $data);
        $processed = 0;
        $skipped = 0;

        foreach ($prospects->cursor() as $prospect) {
            $wasProcessed = match ($data['action']) {
                'approve' => $this->approve($prospect, $request, $lifecycleManager),
                'research_again' => $this->researchAgain($prospect, $request),
                'delete' => $this->delete($prospect),
                'schedule_approved_email' => $this->schedule($prospect, $data['scheduled_send_at'], $request, $sender, $lifecycleManager),
                'send_approved_email' => $this->send($prospect, $request, $sender),
            };

            $wasProcessed ? $processed++ : $skipped++;
        }

        $actionLabel = match ($data['action']) {
            'approve' => 'approved',
            'research_again' => 'queued for research',
            'delete' => 'deleted',
            'schedule_approved_email' => 'scheduled',
            'send_approved_email' => 'sent',
        };
        $message = $processed.' '.str('prospect')->plural($processed).' '.$actionLabel.'.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' skipped because they were not eligible.';
        }

        return back()->with('status', $message);
    }

    /** @param array<string, mixed> $data */
    private function selectedProspects(BulkProspectActionRequest $request, array $data): Builder
    {
        $query = Prospect::query()->accessibleTo($request->user());

        if ($data['selection_scope'] === 'page') {
            return $query->whereKey($data['prospect_ids']);
        }

        return $query
            ->when(filled($data['status'] ?? null), fn (Builder $query) => $query->where('status', $data['status']))
            ->when(filled($data['temperature'] ?? null), fn (Builder $query) => $query->where('lead_temperature', $data['temperature']))
            ->when(filled($data['search'] ?? null), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('business_name', 'like', '%'.$data['search'].'%')
                ->orWhere('email', 'like', '%'.$data['search'].'%')));
    }

    private function approve(Prospect $prospect, BulkProspectActionRequest $request, ProspectLifecycleManager $lifecycleManager): bool
    {
        if (blank($prospect->outreach_subject) || blank($prospect->outreach_body) || $prospect->approved_at || $prospect->sent_at) {
            return false;
        }

        $prospect->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
        $lifecycleManager->markApproved($prospect, $request->user());
        $prospect->recordActivity('approved', 'Outreach email approved in bulk.', $request->user());

        return true;
    }

    private function researchAgain(Prospect $prospect, BulkProspectActionRequest $request): bool
    {
        if (blank($prospect->website_url) || in_array($prospect->analysis_status, ['pending', 'running'], true)) {
            return false;
        }

        $prospect->update(['analysis_status' => 'pending', 'analysis_error' => null, 'scheduled_send_at' => null]);
        $prospect->recordActivity('analysis_queued', 'Website research queued again in bulk.', $request->user());
        AnalyzeProspect::dispatch($prospect);

        return true;
    }

    private function delete(Prospect $prospect): bool
    {
        return (bool) $prospect->delete();
    }

    private function schedule(Prospect $prospect, string $scheduledSendAt, BulkProspectActionRequest $request, ProspectOutreachSender $sender, ProspectLifecycleManager $lifecycleManager): bool
    {
        if ($sender->eligibilityError($prospect) !== null) {
            return false;
        }

        $scheduledFor = CarbonImmutable::parse($scheduledSendAt, 'Europe/London')->utc();
        $prospect->update(['scheduled_send_at' => $scheduledFor]);
        $lifecycleManager->markScheduled($prospect);
        $prospect->recordActivity('send_scheduled', 'Approved outreach email scheduled for '.$scheduledFor->setTimezone('Europe/London')->format('j M Y, H:i').' UK time.', $request->user());
        SendScheduledProspectOutreach::dispatch($prospect->id, $scheduledFor)->delay($scheduledFor)->afterCommit();

        return true;
    }

    private function send(Prospect $prospect, BulkProspectActionRequest $request, ProspectOutreachSender $sender): bool
    {
        if ($sender->eligibilityError($prospect) !== null) {
            return false;
        }

        try {
            $sender->send($prospect, $request->user());
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        return true;
    }
}
