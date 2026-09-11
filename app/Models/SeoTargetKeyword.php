<?php

namespace App\Models;

use Database\Factories\SeoTargetKeywordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class SeoTargetKeyword extends Model
{
    /** @use HasFactory<SeoTargetKeywordFactory> */
    use HasFactory;

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITIES = [self::PRIORITY_HIGH, self::PRIORITY_NORMAL];

    protected $fillable = ['website_id', 'term', 'normalized_term', 'priority', 'note', 'archived_at', 'last_selected_at'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'last_selected_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (SeoTargetKeyword $keyword): void {
            $keyword->term = Str::squish($keyword->term);
            $keyword->normalized_term = self::normalize($keyword->term);
        });
    }

    public static function normalize(string $term): string
    {
        return Str::lower(Str::squish($term));
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(SeoTargetKeywordRanking::class);
    }

    public function latestRanking(): HasOne
    {
        return $this->hasOne(SeoTargetKeywordRanking::class)->latestOfMany('observed_at');
    }

    public function latestSuccessfulRanking(): HasOne
    {
        return $this->hasOne(SeoTargetKeywordRanking::class)->ofMany('observed_at', 'max', fn ($query) => $query->whereIn('status', [SeoTargetKeywordRanking::STATUS_RANKED, SeoTargetKeywordRanking::STATUS_NOT_FOUND]));
    }
}
