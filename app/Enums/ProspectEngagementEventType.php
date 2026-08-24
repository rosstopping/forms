<?php

namespace App\Enums;

enum ProspectEngagementEventType: string
{
    case EmailOpened = 'email_opened';
    case AuditClicked = 'audit_clicked';
    case SitewellClicked = 'sitewell_clicked';
    case PersonalisedVideoClicked = 'personalised_video_clicked';
    case BookingPageClicked = 'booking_page_clicked';
    case ReplyReceived = 'reply_received';
    case ManualAdjustment = 'manual_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::EmailOpened => 'Email opened',
            self::AuditClicked => 'Website audit viewed',
            self::SitewellClicked => 'Sitewell link clicked',
            self::PersonalisedVideoClicked => 'Personalised video viewed',
            self::BookingPageClicked => 'Booking page visited',
            self::ReplyReceived => 'Reply received',
            self::ManualAdjustment => 'Manual score adjustment',
        };
    }
}
