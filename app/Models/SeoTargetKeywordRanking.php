<?php

namespace App\Models;

use Database\Factories\SeoTargetKeywordRankingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoTargetKeywordRanking extends Model
{
    /** @use HasFactory<SeoTargetKeywordRankingFactory> */
    use HasFactory;

    public const STATUS_RANKED = 'ranked';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['seo_target_keyword_id', 'website_id', 'provider', 'location_code', 'language_code', 'device', 'status', 'position', 'ranking_url', 'error', 'cached', 'provider_task_id', 'observed_at'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'location_code' => 'integer', 'cached' => 'boolean', 'observed_at' => 'datetime'];
    }

    public function targetKeyword(): BelongsTo
    {
        return $this->belongsTo(SeoTargetKeyword::class, 'seo_target_keyword_id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
