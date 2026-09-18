<?php

namespace App\Models;

use Database\Factories\SeoImpactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoImpact extends Model
{
    /** @use HasFactory<SeoImpactFactory> */
    use HasFactory;

    protected $fillable = ['automated', 'remediation_run_id', 'verification_status', 'verification', 'next_verification_at', 'verified_at', 'automatic_summary', 'suggested_decision', 'review_available_at', 'acknowledged_at', 'website_id', 'content_request_id', 'content_generation_id', 'source_key', 'title', 'hypothesis', 'target_urls', 'target_queries', 'control_url', 'country', 'device', 'primary_metric', 'business_value', 'confidence', 'effort', 'evidence', 'status', 'live_at', 'confirmed_by', 'deployment_evidence', 'actual_changes', 'property_url', 'baseline', 'observations', 'last_measured_at', 'next_measurement_at', 'review_after_days', 'outcome', 'decision', 'decision_notes', 'reviewed_by', 'reviewed_at', 'measurement_error'];

    protected $attributes = ['automated' => false, 'status' => 'planned', 'primary_metric' => 'clicks', 'business_value' => 3, 'confidence' => 3, 'effort' => 3, 'review_after_days' => 28];

    protected function casts(): array
    {
        return ['automated' => 'boolean', 'verification' => 'array', 'next_verification_at' => 'datetime', 'verified_at' => 'datetime', 'review_available_at' => 'datetime', 'acknowledged_at' => 'datetime', 'target_urls' => 'array', 'target_queries' => 'array', 'evidence' => 'array', 'baseline' => 'array', 'observations' => 'array', 'live_at' => 'datetime', 'last_measured_at' => 'datetime', 'next_measurement_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function contentRequest(): BelongsTo
    {
        return $this->belongsTo(ContentRequest::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(ContentGeneration::class, 'content_generation_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SeoImpactReview::class)->orderBy('checkpoint');
    }

    public function priorityScore(): float
    {
        return round($this->business_value * $this->confidence / max(1, $this->effort), 1);
    }
}
