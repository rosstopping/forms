<?php

namespace App\Models;

use Database\Factories\CompetitorOpportunityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorOpportunity extends Model
{
    /** @use HasFactory<CompetitorOpportunityFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'competitor_audit_id', 'content_request_id', 'fingerprint', 'title', 'status', 'priority_score', 'brief'];

    protected $attributes = ['status' => 'open', 'priority_score' => 0];

    protected function casts(): array
    {
        return ['brief' => 'array'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(CompetitorAudit::class, 'competitor_audit_id');
    }

    public function contentRequest(): BelongsTo
    {
        return $this->belongsTo(ContentRequest::class);
    }
}
