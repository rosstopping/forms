<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProspectLifecycleState;
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
use InvalidArgumentException;
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

        foreach ($prospects->lazyById() as $prospect) {
            $wasProcessed = match ($data['action']) {
                'approve' => $this->approve($prospect, $request, $lifecycleManager),
                'research_again' => $this->researchAgain($prospect, $request),
                'delete' => $this->delete($prospect),
                'schedule_approved_email' => $this->schedule($prospect, $data['scheduled_send_at'], $request, $sender, $lifecycleManager),
                'cancel_scheduled_email' => $this->cancelSchedule($prospect, $request, $lifecycleManager),
                'mark_as_draft' => $this->markAsDraft($prospect, $request, $lifecycleManager),
                'send_approved_email' => $this->send($prospect, $request, $sender),
                'pause', 'resume', 'force_warm', 'force_hot', 'clear_temperature_override', 'stop', 'mark_replied', 'mark_not_interested', 'mark_customer', 'mark_pilot' => $this->applyLifecycleAction($prospect, $data['action'], $request, $lifecycleManager),
            };

            $wasProcessed ? $processed++ : $skipped++;
        }

        $actionLabel = match ($data['action']) {
            'approve' => 'approved',
            'research_again' => 'queued for research',
            'delete' => 'deleted',
            'schedule_approved_email' => 'scheduled',
            'cancel_scheduled_email' => 'schedule cancelled',
            'mark_as_draft' => 'returned to draft',
            'send_approved_email' => 'sent',
            'pause' => 'automation paused',
            'resume' => 'automation resumed',
            'force_warm' => 'forced warm',
            'force_hot' => 'forced hot',
            'clear_temperature_override' => 'returned to automatic scoring',
            'stop' => 'outreach stopped',
            'mark_replied' => 'marked replied',
            'mark_not_interested' => 'marked not interested',
            'mark_customer' => 'moved to customer status',
            'mark_pilot' => 'moved to pilot status',
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
            ->when(filled($data['lifecycle_state'] ?? null), fn (Builder $query) => $query->whereHas('outreachState', fn (Builder $query) => $query->where('lifecycle_state', $data['lifecycle_state'])))
            ->when(($data['email_status'] ?? null) === 'missing', fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('email')->orWhere('email', '')))
            ->when(($data['email_status'] ?? null) === 'present', fn (Builder $query) => $query->whereNotNull('email')->where('email', '!=', ''))
            ->when(filled($data['search'] ?? null), fn (Builder $query) => $query->matchingSearchTerms($data['search']));
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

    private function cancelSchedule(Prospect $prospect, BulkProspectActionRequest $request, ProspectLifecycleManager $lifecycleManager): bool
    {
        if ($prospect->scheduled_send_at === null || $prospect->sent_at !== null) {
            return false;
        }

        $prospect->update(['scheduled_send_at' => null]);
        $lifecycleManager->markUnscheduled($prospect);
        $prospect->recordActivity('send_schedule_cancelled', 'Scheduled outreach cancelled in bulk.', $request->user());

        return true;
    }

    private function markAsDraft(Prospect $prospect, BulkProspectActionRequest $request, ProspectLifecycleManager $lifecycleManager): bool
    {
        $isApprovedOrScheduled = $prospect->status === 'approved'
            || $prospect->approved_at !== null
            || $prospect->scheduled_send_at !== null;

        if (! $isApprovedOrScheduled || $prospect->sent_at !== null) {
            return false;
        }

        $prospect->update(['status' => 'drafted', 'approved_at' => null, 'approved_by' => null, 'scheduled_send_at' => null]);
        $lifecycleManager->markDrafted($prospect);
        $prospect->recordActivity('returned_to_draft', 'Approved outreach returned to draft in bulk.', $request->user());

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

    private function applyLifecycleAction(Prospect $prospect, string $action, BulkProspectActionRequest $request, ProspectLifecycleManager $lifecycleManager): bool
    {
        try {
            match ($action) {
                'pause' => $lifecycleManager->pause($prospect, $request->user()),
                'resume' => $lifecycleManager->resume($prospect, $request->user()),
                'force_warm' => $lifecycleManager->forceTemperature($prospect, 'warm', $request->user()),
                'force_hot' => $lifecycleManager->forceTemperature($prospect, 'hot', $request->user()),
                'clear_temperature_override' => $lifecycleManager->clearTemperatureOverride($prospect, $request->user()),
                'stop' => $lifecycleManager->stop($prospect, $request->user()),
                'mark_replied' => $lifecycleManager->transitionManually($prospect, ProspectLifecycleState::Replied, $request->user()),
                'mark_not_interested' => $lifecycleManager->transitionManually($prospect, ProspectLifecycleState::NotInterested, $request->user()),
                'mark_customer' => $lifecycleManager->transitionManually($prospect, ProspectLifecycleState::Customer, $request->user()),
                'mark_pilot' => $lifecycleManager->transitionManually($prospect, ProspectLifecycleState::Pilot, $request->user()),
            };
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }
}
