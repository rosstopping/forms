<?php

namespace App\Enums;

enum OptimisationStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Deployed = 'deployed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';
}
