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

    protected $fillable = ['budget_reserved_at', 'budget_work_type', 'discovery_context', 'planning_status', 'planned_for', 'work_type', 'held_at', 'hold_reason', 'dependencies', 'preflight', 'action_fingerprint', 'backlink_context', 'backlink_fingerprint', 'competitor_context', 'competitor_fingerprint', 'website_id', 'created_by', 'content_generation_id', 'instructions', 'picked_up_at', 'bumped_at', 'pixel_processed_at', 'pixel_error', 'manual_taken_by', 'manual_started_at', 'manual_completed_at', 'manual_prompt'];

    protected $attributes = ['work_type' => 'unspecified', 'planning_status' => 'queued'];

    protected function casts(): array
    {
        return ['budget_reserved_at' => 'datetime', 'discovery_context' => 'array', 'planned_for' => 'date', 'held_at' => 'datetime', 'dependencies' => 'array', 'preflight' => 'array', 'backlink_context' => 'array', 'competitor_context' => 'array', 'picked_up_at' => 'datetime', 'bumped_at' => 'datetime', 'pixel_processed_at' => 'datetime', 'manual_started_at' => 'datetime', 'manual_completed_at' => 'datetime'];
    }

    public function scopePendingInQueueOrder(Builder $query): Builder
    {
        return $query
            ->where('planning_status', 'queued')->whereNull('picked_up_at')
            ->orderByRaw('CASE WHEN bumped_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('bumped_at')
            ->oldest('created_at')
            ->oldest('id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function manualAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manual_taken_by');
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

    public function seoOpportunities(): HasMany
    {
        return $this->hasMany(SeoOpportunity::class);
    }

    public function seoImpact(): HasOne
    {
        return $this->hasOne(SeoImpact::class);
    }

    public function optimisations(): HasMany
    {
        return $this->hasMany(Optimisation::class);
    }
}
