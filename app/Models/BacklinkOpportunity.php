<?php

namespace App\Models;

use Database\Factories\BacklinkOpportunityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacklinkOpportunity extends Model
{
    /** @use HasFactory<BacklinkOpportunityFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'backlink_audit_id', 'content_request_id', 'fingerprint', 'type', 'status', 'title', 'priority_score', 'evidence'];

    protected function casts(): array
    {
        return ['evidence' => 'array'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(BacklinkAudit::class, 'backlink_audit_id');
    }

    public function contentRequest(): BelongsTo
    {
        return $this->belongsTo(ContentRequest::class);
    }
}
