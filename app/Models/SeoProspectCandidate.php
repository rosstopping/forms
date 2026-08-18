<?php

namespace App\Models;

use Database\Factories\SeoProspectCandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoProspectCandidate extends Model
{
    /** @use HasFactory<SeoProspectCandidateFactory> */
    use HasFactory;

    protected $fillable = ['seo_prospect_search_id', 'prospect_id', 'domain', 'website_url', 'business_name', 'location', 'page_count', 'audit_score', 'migration_difficulty', 'migration_difficulty_reason', 'opportunity_score', 'score_breakdown', 'observations', 'qualification_status'];

    protected $attributes = ['migration_difficulty' => 'unknown', 'qualification_status' => 'pending_analysis'];

    protected function casts(): array
    {
        return ['score_breakdown' => 'array', 'observations' => 'array'];
    }

    public function search(): BelongsTo
    {
        return $this->belongsTo(SeoProspectSearch::class, 'seo_prospect_search_id');
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(SeoProspectRanking::class);
    }
}
