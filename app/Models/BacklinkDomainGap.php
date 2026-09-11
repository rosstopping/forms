<?php

namespace App\Models;

use Database\Factories\BacklinkDomainGapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacklinkDomainGap extends Model
{
    /** @use HasFactory<BacklinkDomainGapFactory> */
    use HasFactory;

    protected $fillable = ['backlink_audit_id', 'prospect_id', 'domain', 'domain_rank', 'spam_score', 'competitor_count', 'competitor_evidence', 'priority_score'];

    protected function casts(): array
    {
        return ['competitor_evidence' => 'array'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(BacklinkAudit::class, 'backlink_audit_id');
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
