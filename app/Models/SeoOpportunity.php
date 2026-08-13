<?php

namespace App\Models;

use Database\Factories\SeoOpportunityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoOpportunity extends Model
{
    public const TYPE_STRIKING_DISTANCE = 'striking_distance';

    public const TYPE_HIGH_VOLUME = 'high_volume';

    public const TYPE_COMMERCIAL = 'commercial';

    public const TYPE_DECLINING = 'declining';

    public const TYPE_IMPROVING = 'improving';

    public const STATUS_OPEN = 'open';

    public const STATUS_QUEUED = 'queued';

    /** @use HasFactory<SeoOpportunityFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'content_request_id', 'seo_snapshot_id', 'seo_keyword_id', 'fingerprint', 'type', 'status', 'title', 'summary', 'recommendation', 'metrics', 'priority_score'];

    protected $attributes = ['status' => self::STATUS_OPEN];

    protected function casts(): array
    {
        return ['metrics' => 'array', 'priority_score' => 'decimal:4'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function contentRequest(): BelongsTo
    {
        return $this->belongsTo(ContentRequest::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SeoSnapshot::class, 'seo_snapshot_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'seo_keyword_id');
    }
}
