<?php

namespace App\Services\SeoIntelligence;

use App\Models\SeoSnapshot;

final readonly class SeoRefreshResult
{
    public const REASON_QUEUED = 'queued';

    public const REASON_FRESH = 'fresh';

    public const REASON_IN_PROGRESS = 'in_progress';

    public function __construct(
        public SeoSnapshot $snapshot,
        public bool $queued,
        public string $reason,
    ) {}
}
