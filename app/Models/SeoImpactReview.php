<?php

namespace App\Models;

use Database\Factories\SeoImpactReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoImpactReview extends Model
{
    /** @use HasFactory<SeoImpactReviewFactory> */
    use HasFactory;

    protected $fillable = ['seo_impact_id', 'checkpoint', 'period_start', 'period_end', 'baseline', 'measurement', 'assessment', 'outcome'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'baseline' => 'array', 'measurement' => 'array', 'assessment' => 'array'];
    }

    public function impact(): BelongsTo
    {
        return $this->belongsTo(SeoImpact::class, 'seo_impact_id');
    }
}
