<?php

namespace App\Models;

use Database\Factories\SeoSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoSnapshot extends Model
{
    public const PROVIDER_DATAFORSEO = 'dataforseo';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const STATUS_FAILED = 'failed';

    /** @use HasFactory<SeoSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'website_id', 'provider', 'domain', 'location_code', 'language_code', 'status',
        'organic_keywords', 'estimated_organic_traffic', 'top_3_keywords', 'top_10_keywords',
        'top_20_keywords', 'top_100_keywords', 'backlinks', 'referring_domains', 'referring_ips',
        'referring_subnets', 'broken_backlinks', 'domain_rank', 'snapshot_date', 'metadata', 'errors',
        'started_at', 'completed_at',
    ];

    protected $attributes = [
        'provider' => self::PROVIDER_DATAFORSEO,
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'estimated_organic_traffic' => 'decimal:4',
            'snapshot_date' => 'date',
            'metadata' => 'array',
            'errors' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(SeoKeyword::class);
    }

    public function referringDomains(): HasMany
    {
        return $this->hasMany(SeoReferringDomain::class);
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(SeoCompetitor::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(SeoOpportunity::class);
    }

    public function apiUsages(): HasMany
    {
        return $this->hasMany(ExternalApiUsage::class);
    }
}
