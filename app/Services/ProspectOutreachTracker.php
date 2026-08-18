<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;
use App\Models\ProspectOutreachLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class ProspectOutreachTracker
{
    public function createDelivery(Prospect $prospect): ProspectOutreachDelivery
    {
        return DB::transaction(function () use ($prospect): ProspectOutreachDelivery {
            $delivery = $prospect->outreachDeliveries()->create(['recipient_email' => $prospect->email]);

            if (filled($prospect->showcase_video_url)) {
                $delivery->links()->create([
                    'kind' => 'showcase_video',
                    'label' => 'Website video',
                    'destination_url' => $prospect->showcase_video_url,
                ]);
            }

            if (filled($prospect->website_url) && $prospect->analysed_at !== null) {
                $delivery->links()->create([
                    'kind' => 'website_audit',
                    'label' => 'Website audit',
                    'destination_url' => URL::temporarySignedRoute('prospect-reports.show', now()->addDays(30), ['prospect' => $prospect]),
                ]);
            }

            $delivery->links()->create([
                'kind' => 'book_call',
                'label' => 'Book a call',
                'destination_url' => 'https://cal.com/ross',
            ]);

            return $delivery->load('links');
        });
    }

    public function recordOpen(ProspectOutreachDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery): void {
            $delivery = ProspectOutreachDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $openedAt = now();
            $firstOpen = $delivery->first_opened_at === null;
            $delivery->update([
                'first_opened_at' => $delivery->first_opened_at ?? $openedAt,
                'last_opened_at' => $openedAt,
                'open_count' => $delivery->open_count + 1,
            ]);
            $this->warmProspectFromOpen($delivery->prospect);

            if ($firstOpen) {
                $delivery->prospect->recordActivity('email_opened', 'Outreach email opened.');
            }
        });
    }

    public function recordClick(ProspectOutreachLink $link): void
    {
        DB::transaction(function () use ($link): void {
            $link = ProspectOutreachLink::query()->lockForUpdate()->findOrFail($link->id);
            $delivery = ProspectOutreachDelivery::query()->lockForUpdate()->findOrFail($link->prospect_outreach_delivery_id);
            $clickedAt = now();
            $firstLinkClick = $link->first_clicked_at === null;

            $link->update([
                'first_clicked_at' => $link->first_clicked_at ?? $clickedAt,
                'last_clicked_at' => $clickedAt,
                'click_count' => $link->click_count + 1,
            ]);
            $delivery->update([
                'first_clicked_at' => $delivery->first_clicked_at ?? $clickedAt,
                'last_clicked_at' => $clickedAt,
                'click_count' => $delivery->click_count + 1,
            ]);
            $this->markProspectHot($delivery->prospect);

            if ($firstLinkClick) {
                $delivery->prospect->recordActivity('email_clicked', 'Clicked the “'.$link->label.'” link in the outreach email.');
            }
        });
    }

    private function warmProspectFromOpen(Prospect $prospect): void
    {
        if ($prospect->lead_temperature === 'cold') {
            $prospect->update(['lead_temperature' => 'warm']);
        }
    }

    private function markProspectHot(Prospect $prospect): void
    {
        if ($prospect->lead_temperature !== 'hot') {
            $prospect->update(['lead_temperature' => 'hot']);
        }
    }
}
