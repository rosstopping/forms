<?php

namespace App\Models;

use Database\Factories\SeoKeywordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoKeyword extends Model
{
    /** @use HasFactory<SeoKeywordFactory> */
    use HasFactory;

    protected $fillable = [
        'website_id', 'seo_snapshot_id', 'fingerprint', 'keyword', 'position', 'previous_position',
        'ranking_url', 'search_volume', 'cpc', 'competition', 'competition_level', 'search_intent',
        'estimated_traffic', 'keyword_difficulty', 'location_code', 'language_code',
    ];

    protected function casts(): array
    {
        return ['cpc' => 'decimal:4', 'competition' => 'decimal:5', 'estimated_traffic' => 'decimal:4'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SeoSnapshot::class, 'seo_snapshot_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(SeoOpportunity::class);
    }
}
