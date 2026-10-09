<?php

namespace App\Models;

use Database\Factories\ContentOpportunityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentOpportunity extends Model
{
    /** @use HasFactory<ContentOpportunityFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'content_request_id', 'fingerprint', 'title', 'status', 'priority_score', 'brief', 'collected_at'];

    protected $attributes = ['status' => 'open', 'priority_score' => 40];

    protected function casts(): array
    {
        return ['brief' => 'array', 'collected_at' => 'datetime', 'priority_score' => 'integer'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function contentRequest(): BelongsTo
    {
        return $this->belongsTo(ContentRequest::class);
    }
}
