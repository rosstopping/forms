<?php

namespace App\Models;

use Database\Factories\CompetitorAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitorAudit extends Model
{
    /** @use HasFactory<CompetitorAuditFactory> */
    use HasFactory;

    protected $fillable = ['website_competitor_id', 'website_id', 'domain', 'competitor_domain', 'provider', 'location_code', 'language_code', 'status', 'stages', 'errors', 'limits', 'started_at', 'completed_at'];

    protected $attributes = ['status' => 'pending', 'provider' => 'dataforseo'];

    protected function casts(): array
    {
        return ['stages' => 'array', 'errors' => 'array', 'limits' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(WebsiteCompetitor::class, 'website_competitor_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(CompetitorKeyword::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(CompetitorPage::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(CompetitorOpportunity::class);
    }
}
