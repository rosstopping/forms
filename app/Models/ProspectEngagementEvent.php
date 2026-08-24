<?php

namespace App\Models;

use App\Enums\ProspectEngagementEventType;
use Database\Factories\ProspectEngagementEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectEngagementEvent extends Model
{
    /** @use HasFactory<ProspectEngagementEventFactory> */
    use HasFactory;

    protected $fillable = [
        'prospect_id', 'prospect_outreach_delivery_id', 'prospect_outreach_link_id', 'event_type',
        'source', 'fingerprint', 'score_delta', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => ProspectEngagementEventType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(ProspectOutreachDelivery::class, 'prospect_outreach_delivery_id');
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(ProspectOutreachLink::class, 'prospect_outreach_link_id');
    }
}
