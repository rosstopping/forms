<?php

namespace App\Models;

use Database\Factories\BacklinkAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BacklinkAudit extends Model
{
    /** @use HasFactory<BacklinkAuditFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'domain', 'provider', 'status', 'stages', 'errors', 'limits', 'overview', 'new_lost_trend', 'started_at', 'completed_at'];

    protected $attributes = ['provider' => 'dataforseo', 'status' => 'pending'];

    protected function casts(): array
    {
        return ['stages' => 'array', 'errors' => 'array', 'limits' => 'array', 'overview' => 'array', 'new_lost_trend' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(BacklinkAuditCompetitor::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(BacklinkLink::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(BacklinkPage::class);
    }

    public function domainGaps(): HasMany
    {
        return $this->hasMany(BacklinkDomainGap::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(BacklinkOpportunity::class);
    }
}
