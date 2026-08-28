<?php

namespace App\Services;

use App\Enums\ProspectEngagementEventType;
use App\Enums\ProspectOutreachMessageType;
use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;
use App\Models\ProspectOutreachLink;
use App\Models\User;
use App\Notifications\ProspectBecameHot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

class ProspectOutreachTracker
{
    public function __construct(private ProspectEngagementScorer $engagementScorer) {}

    public function createDelivery(
        Prospect $prospect,
        ProspectOutreachMessageType $messageType = ProspectOutreachMessageType::Initial,
        ?string $subject = null,
        ?string $body = null,
        ?string $idempotencyKey = null,
    ): ProspectOutreachDelivery {
        return DB::transaction(function () use ($prospect, $messageType, $subject, $body, $idempotencyKey): ProspectOutreachDelivery {
            $attributes = [
                'recipient_email' => $prospect->email,
                'message_type' => $messageType,
                'status' => 'pending',
                'subject' => $subject ?? $prospect->outreach_subject,
                'body' => $body ?? $prospect->outreach_body,
            ];
            $delivery = $idempotencyKey
                ? $prospect->outreachDeliveries()->firstOrCreate(['idempotency_key' => $idempotencyKey], $attributes)
                : $prospect->outreachDeliveries()->create($attributes);

            if ($delivery->links()->exists()) {
                return $delivery->load('links');
            }

            if ($messageType === ProspectOutreachMessageType::Initial) {
                return $delivery->load('links');
            }

            if ($messageType !== ProspectOutreachMessageType::PostVideoFollowUp && filled($prospect->showcase_video_url)) {
                $delivery->links()->create([
                    'kind' => 'showcase_video',
                    'label' => 'Website video',
                    'destination_url' => $prospect->showcase_video_url,
                ]);
            }

            if ($messageType !== ProspectOutreachMessageType::PostVideoFollowUp && filled($prospect->website_url) && $prospect->analysed_at !== null) {
                $auditLink = $delivery->links()->create([
                    'kind' => 'website_audit',
                    'label' => 'Website audit',
                    'destination_url' => $prospect->website_url,
                ]);
                $auditLink->update([
                    'destination_url' => URL::temporarySignedRoute('prospect-reports.show', now()->addDays(30), [
                        'prospect' => $prospect,
                        'outreach_link' => $auditLink->uuid,
                    ]),
                ]);
            }

            if ($messageType !== ProspectOutreachMessageType::PostVideoFollowUp) {
                $delivery->links()->create([
                    'kind' => 'book_call',
                    'label' => 'Book a call',
                    'destination_url' => 'https://cal.com/ross',
                ]);
            }

            return $delivery->load('links');
        });
    }

    public function recordOpen(ProspectOutreachDelivery $delivery, string $source = 'tracking'): void
    {
        DB::transaction(function () use ($delivery, $source): void {
            $delivery = ProspectOutreachDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $openedAt = now();
            $firstOpen = $delivery->first_opened_at === null;
            $openCount = $delivery->open_count + 1;
            $delivery->update([
                'first_opened_at' => $delivery->first_opened_at ?? $openedAt,
                'last_opened_at' => $openedAt,
                'open_count' => $openCount,
            ]);
            $this->engagementScorer->record(
                $delivery->prospect,
                ProspectEngagementEventType::EmailOpened,
                'delivery:'.$delivery->uuid.':open:'.$openCount.':'.$source,
                ['delivery_uuid' => $delivery->uuid],
                $source,
                $openedAt,
                $delivery->id,
            );

            if ($firstOpen) {
                $delivery->prospect->recordActivity('email_opened', 'Outreach email opened.');
            }
        });
    }

    public function recordClick(ProspectOutreachLink $link, string $source = 'tracking'): void
    {
        $hotProspect = DB::transaction(function () use ($link, $source): ?Prospect {
            $link = ProspectOutreachLink::query()->lockForUpdate()->findOrFail($link->id);
            $delivery = ProspectOutreachDelivery::query()->lockForUpdate()->findOrFail($link->prospect_outreach_delivery_id);
            $prospect = Prospect::query()->lockForUpdate()->findOrFail($delivery->prospect_id);
            $clickedAt = now();
            $firstLinkClick = $link->first_clicked_at === null;
            $clickCount = $link->click_count + 1;
            $wasHot = $prospect->lead_temperature === 'hot';

            $link->update([
                'first_clicked_at' => $link->first_clicked_at ?? $clickedAt,
                'last_clicked_at' => $clickedAt,
                'click_count' => $clickCount,
            ]);
            $delivery->update([
                'first_clicked_at' => $delivery->first_clicked_at ?? $clickedAt,
                'last_clicked_at' => $clickedAt,
                'click_count' => $delivery->click_count + 1,
            ]);
            $eventType = $this->eventTypeForLink($link);

            if ($eventType) {
                $this->engagementScorer->record(
                    $prospect,
                    $eventType,
                    'link:'.$link->uuid.':click:'.$clickCount.':'.$source,
                    ['link_kind' => $link->kind, 'link_label' => $link->label],
                    $source,
                    $clickedAt,
                    $delivery->id,
                    $link->id,
                );
            }

            if ($firstLinkClick) {
                $prospect->recordActivity('email_clicked', 'Clicked the “'.$link->label.'” link in the outreach email.');
            }

            return ! $wasHot && $prospect->fresh()->lead_temperature === 'hot' ? $prospect : null;
        });

        if ($hotProspect) {
            $this->notifyHotProspect($hotProspect, $link->label);
        }
    }

    public function recordReportVisit(ProspectOutreachLink $link, string $source = 'tracking'): void
    {
        if ($link->kind !== 'website_audit') {
            return;
        }

        $prospect = $link->delivery->prospect;
        $wasHot = $prospect->lead_temperature === 'hot';
        $this->engagementScorer->record(
            $prospect,
            ProspectEngagementEventType::AuditClicked,
            'report:'.$link->uuid.':visit:'.now()->format('YmdHi').':'.$source,
            ['link_kind' => $link->kind, 'link_label' => $link->label, 'report_visit' => true],
            $source,
            now(),
            $link->prospect_outreach_delivery_id,
            $link->id,
        );

        if (! $wasHot && $prospect->fresh()->lead_temperature === 'hot') {
            $this->notifyHotProspect($prospect, 'Website audit revisit');
        }
    }

    private function eventTypeForLink(ProspectOutreachLink $link): ?ProspectEngagementEventType
    {
        return match ($link->kind) {
            'website_audit' => ProspectEngagementEventType::AuditClicked,
            'showcase_video' => ProspectEngagementEventType::PersonalisedVideoClicked,
            'book_call' => ProspectEngagementEventType::BookingPageClicked,
            'sitewell', 'sitewell_website', 'pricing' => ProspectEngagementEventType::SitewellClicked,
            default => null,
        };
    }

    private function notifyHotProspect(Prospect $prospect, string $signalLabel): void
    {
        Notification::send(
            User::query()->where('role', User::ROLE_ADMIN)->get(),
            new ProspectBecameHot($prospect, $signalLabel),
        );
    }
}
