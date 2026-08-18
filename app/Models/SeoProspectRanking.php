<?php

namespace App\Models;

use Database\Factories\SeoProspectRankingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoProspectRanking extends Model
{
    /** @use HasFactory<SeoProspectRankingFactory> */
    use HasFactory;

    protected $fillable = ['seo_prospect_candidate_id', 'keyword', 'position', 'ranking_url', 'page_title', 'description', 'checked_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(SeoProspectCandidate::class, 'seo_prospect_candidate_id');
    }
}
