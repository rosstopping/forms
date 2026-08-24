<?php

namespace App\Enums;

enum ProspectOutreachStopReason: string
{
    case Replied = 'replied';
    case NotInterested = 'not_interested';
    case FutureOpportunity = 'future_opportunity';
    case Customer = 'customer';
    case Pilot = 'pilot';
    case Closed = 'closed';
    case Exhausted = 'exhausted';
    case Suppressed = 'suppressed';
    case Manual = 'manual';
    case InboundLead = 'inbound_lead';
}
