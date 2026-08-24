<?php

namespace App\Enums;

enum ProspectLifecycleState: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case InitialEmailSent = 'initial_email_sent';
    case Cold = 'cold';
    case Warm = 'warm';
    case Hot = 'hot';
    case NeedsPersonalisedVideo = 'needs_personalised_video';
    case VideoSent = 'video_sent';
    case HighlyEngaged = 'highly_engaged';
    case Replied = 'replied';
    case NotInterested = 'not_interested';
    case FutureOpportunity = 'future_opportunity';
    case Customer = 'customer';
    case Pilot = 'pilot';
    case Closed = 'closed';
    case Exhausted = 'exhausted';

    public function stopsNormalOutreach(): bool
    {
        return in_array($this, [
            self::Replied,
            self::NotInterested,
            self::FutureOpportunity,
            self::Customer,
            self::Pilot,
            self::Closed,
            self::Exhausted,
        ], true);
    }
}
