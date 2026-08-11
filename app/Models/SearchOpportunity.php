<?php

namespace App\Models;

use Database\Factories\SearchOpportunityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchOpportunity extends Model
{
    /** @use HasFactory<SearchOpportunityFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = ['website_id', 'content_request_id', 'fingerprint', 'type', 'status', 'query', 'page', 'title', 'summary', 'recommendation', 'metrics', 'priority_score', 'first_detected_at', 'last_detected_at'];

    protected $attributes = ['status' => self::STATUS_OPEN];

    protected function casts(): array
    {
        return ['metrics' => 'array', 'priority_score' => 'decimal:2', 'first_detected_at' => 'datetime', 'last_detected_at' => 'datetime'];
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
