<?php

namespace App\Models;

use Database\Factories\ContentRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContentRequest extends Model
{
    /** @use HasFactory<ContentRequestFactory> */
    use HasFactory;

    protected $fillable = ['backlink_context', 'backlink_fingerprint', 'competitor_context', 'competitor_fingerprint', 'website_id', 'created_by', 'content_generation_id', 'instructions', 'picked_up_at', 'bumped_at', 'pixel_processed_at', 'pixel_error'];

    protected function casts(): array
    {
        return ['backlink_context' => 'array', 'competitor_context' => 'array', 'picked_up_at' => 'datetime', 'bumped_at' => 'datetime', 'pixel_processed_at' => 'datetime'];
    }

    public function scopePendingInQueueOrder(Builder $query): Builder
    {
        return $query
            ->whereNull('picked_up_at')
            ->orderByRaw('CASE WHEN bumped_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('bumped_at')
            ->oldest('created_at')
            ->oldest('id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(ContentGeneration::class, 'content_generation_id');
    }

    public function searchOpportunity(): HasOne
    {
        return $this->hasOne(SearchOpportunity::class);
    }

    public function seoOpportunity(): HasOne
    {
        return $this->hasOne(SeoOpportunity::class);
    }

    public function optimisations(): HasMany
    {
        return $this->hasMany(Optimisation::class);
    }
}
