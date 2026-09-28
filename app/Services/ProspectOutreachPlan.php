<?php

namespace App\Services;

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectOutreachMessageType;
use App\Enums\ProspectSequenceStep;
use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;
use Carbon\CarbonInterface;

class ProspectOutreachPlan
{
    public function __construct(private ProspectOutreachContent $content, private ProspectOutreachEligibility $eligibility) {}

    /** @return array{next: string, reason: string, video_in_initial: bool, rows: list<array{title: string, status: string, timing: string, subject: ?string, body: ?string, note: string, blocks: string}>} */
    public function forProspect(Prospect $prospect): array
    {
        $prospect->loadMissing(['outreachState', 'outreachDeliveries.links']);
        $state = $prospect->outreachState;
        $initial = $this->content->initialDelivery($prospect);
        $initialSent = $initial?->sent_at ?? $state->initial_email_sent_at ?? $prospect->sent_at;
        $cold = $this->delivery($prospect, ProspectOutreachMessageType::ColdFollowUp);
        $final = $this->delivery($prospect, ProspectOutreachMessageType::FinalFollowUp);
        $video = $this->delivery($prospect, ProspectOutreachMessageType::PersonalisedVideo);
        $coldDays = (int) config('outreach.timing.cold_retry_days', 4);
        $finalDays = (int) config('outreach.timing.final_follow_up_days', 6);
        $finished = in_array($state->sequence_step, [ProspectSequenceStep::PersonalisedVideo, ProspectSequenceStep::PostVideoFollowUp, ProspectSequenceStep::FinalFollowUp, ProspectSequenceStep::Complete], true);
        $reason = $this->eligibility->manualMessageError($prospect);
        if ($prospect->approved_at === null && $prospect->suppressed_at === null && ! $state->lifecycle_state->stopsNormalOutreach() && $state->automation_status !== ProspectAutomationStatus::Stopped) {
            $reason = 'Save and approve the initial draft before sending or scheduling.';
        }
        $sendReason = $reason ?? (blank($prospect->website_url) && blank($prospect->showcase_video_url) ? 'Add a showcase video before sending outreach for a prospect without a website.' : null);
        $autoReason = $sendReason ?? match (true) {
            $state->automation_status === ProspectAutomationStatus::Paused => 'Automation is paused. No automatic follow-up will send.',
            ! config('outreach.automatic_follow_ups_enabled', true) => 'Automatic follow-ups are switched off globally.',
            $state->engagement_score >= (int) config('outreach.temperature_thresholds.warm', 3) => 'Meaningful engagement has paused the cold email sequence.',
            $state->follow_up_attempts >= (int) config('outreach.maximum_follow_up_attempts', 2) => 'The maximum number of automatic follow-ups has been reached.',
            $finished => 'No further automatic email is planned.',
            default => null,
        };
        $initialReason = $sendReason ?? ($state->automation_status !== ProspectAutomationStatus::Active ? 'Automation is paused. Resume it before sending the initial email.' : null);
        $next = 'No automatic email planned';
        $summary = $autoReason ?? ($initialSent ? 'The next follow-up sends only while outreach remains eligible.' : 'Send now or choose a time after approving the draft.');
        $rows = [];
        $rows[] = [
            'title' => 'Initial email', 'status' => $initialSent ? 'Sent' : ($initialReason ? 'Needs attention' : ($prospect->scheduled_send_at ? 'Scheduled' : 'Ready')),
            'timing' => $initialSent ? $this->date($initialSent) : ($prospect->scheduled_send_at ? $this->date($prospect->scheduled_send_at) : 'You choose when'),
            'subject' => $initial?->subject ?? $prospect->outreach_subject, 'body' => $initial?->body ?? $prospect->outreach_body,
            'note' => $initialSent ? 'The saved record of the email that was sent.' : 'The saved draft. Save edits before testing or approving.',
            'blocks' => $initial ? $this->blocks($initial) : $this->draftBlocks($prospect),
        ];
        if (! $initialSent && $prospect->scheduled_send_at && ! $initialReason) {
            $next = 'Initial email · '.$this->date($prospect->scheduled_send_at);
            $summary = 'Scheduled initial email. Follow-up dates start from successful delivery.';
        }
        foreach ([
            [ProspectOutreachMessageType::ColdFollowUp, 'First follow-up', $cold, ProspectSequenceStep::InitialEmail, $coldDays.' days after the initial email'],
            [ProspectOutreachMessageType::FinalFollowUp, 'Final follow-up', $final, ProspectSequenceStep::ColdFollowUp, $finalDays.' days after the first follow-up'],
        ] as [$type, $title, $delivery, $expectedStep, $relativeTime]) {
            $message = $this->content->followUp($prospect, $type, true);
            $sent = $delivery?->sent_at;
            $isNext = ! $sent && ! $autoReason && $state->sequence_step === $expectedStep && $state->next_action_at !== null;
            $limit = (int) config('outreach.maximum_follow_up_attempts', 2);
            $enabledStep = $type === ProspectOutreachMessageType::ColdFollowUp ? $limit >= 1 : $limit >= 2;
            $status = $sent ? 'Sent' : (! $enabledStep || $finished ? 'Not scheduled' : ($autoReason ? 'On hold' : ($isNext ? 'Scheduled' : 'Conditional')));
            $timing = $sent ? $this->date($sent) : ($isNext && $enabledStep ? $this->date($state->next_action_at) : $relativeTime);
            $rows[] = [
                'title' => $title, 'status' => $status, 'timing' => $timing,
                'subject' => $delivery?->subject ?? $message['subject'], 'body' => $delivery?->body ?? $message['body'],
                'note' => $sent ? 'The saved record of the email that was sent.' : ($autoReason ?? 'Only sends if there is no reply or meaningful engagement and outreach is still approved.'),
                'blocks' => $delivery ? $this->blocks($delivery) : $this->followUpBlocks($prospect),
            ];
            if ($isNext && $enabledStep) {
                $next = $title.' · '.$timing;
            }
        }
        if ($video || $state->video_sent_at || $state->sequence_step === ProspectSequenceStep::AwaitingPersonalisedVideo) {
            $scheduled = $video?->status === 'scheduled' && $video->scheduled_at !== null;
            $draft = $state->personalised_video_draft;
            $rows[] = [
                'title' => 'Personalised video', 'status' => $state->video_sent_at ? 'Sent' : ($scheduled ? ($reason ? 'On hold' : 'Scheduled') : 'Manual action'),
                'timing' => $state->video_sent_at ? $this->date($state->video_sent_at) : ($scheduled ? $this->date($video->scheduled_at) : 'You choose when'),
                'subject' => $video?->subject ?? ($draft['subject'] ?? null), 'body' => $video?->body ?? ($draft['body'] ?? null),
                'note' => 'Separate from an initial email containing a video. No automatic follow-up is sent after this message.',
                'blocks' => $video ? $this->blocks($video) : 'Video · booking & phone · available audit · Digizu footer',
            ];
            if ($scheduled && ! $reason) {
                $next = 'Personalised video · '.$this->date($video->scheduled_at);
                $summary = 'Your scheduled video sends even while cold automation is paused. No automatic email follows it.';
            }
        }

        return ['next' => $next, 'reason' => $summary, 'video_in_initial' => $this->content->initialIncludedVideo($prospect, true), 'rows' => $rows];
    }

    private function delivery(Prospect $prospect, ProspectOutreachMessageType $type): ?ProspectOutreachDelivery
    {
        return $prospect->outreachDeliveries->where('message_type', $type)->sortByDesc('id')->first();
    }

    private function date(CarbonInterface $date): string
    {
        return $date->copy()->setTimezone('Europe/London')->format('j M Y, H:i').' UK';
    }

    private function blocks(ProspectOutreachDelivery $delivery): string
    {
        return $delivery->links->map(fn ($link): string => match ($link->kind) {
            'showcase_video' => 'Video', 'website_audit' => 'Site audit', 'book_call' => 'Booking & phone', default => $link->label,
        })->push('Digizu footer')->implode(' · ');
    }

    private function draftBlocks(Prospect $prospect): string
    {
        return collect([
            filled($prospect->showcase_video_url) ? 'Video' : null,
            $prospect->include_site_audit && filled($prospect->website_url) && $prospect->analysed_at ? 'Site audit' : null,
            filled($prospect->showcase_video_url) ? 'Booking & phone' : null,
            'Digizu footer',
        ])->filter()->implode(' · ');
    }

    private function followUpBlocks(Prospect $prospect): string
    {
        return collect([
            filled($prospect->showcase_video_url) ? 'Video' : null, 'Booking & phone',
            filled($prospect->website_url) && $prospect->analysed_at ? 'Site audit' : null, 'Digizu footer',
        ])->filter()->implode(' · ');
    }
}
