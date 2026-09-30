<?php

namespace App\Events;

use App\Models\MarketingConversion;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketingConversionRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public MarketingConversion $conversion) {}
}
