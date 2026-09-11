<?php

namespace App\Models;

use Database\Factories\WebsiteCompetitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WebsiteCompetitor extends Model
{
    /** @use HasFactory<WebsiteCompetitorFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'domain', 'excluded', 'source'];

    protected $attributes = ['excluded' => false, 'source' => 'manual'];

    protected function casts(): array
    {
        return ['excluded' => 'boolean'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(CompetitorAudit::class);
    }

    public function latestAudit(): HasOne
    {
        return $this->hasOne(CompetitorAudit::class)->latestOfMany();
    }
}
