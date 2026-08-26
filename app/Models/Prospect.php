<?php

namespace App\Models;

use Database\Factories\ProspectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prospect extends Model
{
    /** @use HasFactory<ProspectFactory> */
    use HasFactory;

    public const STATUSES = ['new', 'researched', 'drafted', 'approved', 'contacted', 'replied', 'converted', 'not_interested'];

    public const LEAD_TEMPERATURES = ['cold', 'warm', 'hot'];

    protected $fillable = ['user_id', 'website_id', 'prospecting_industry_profile_id', 'prospecting_location_id', 'business_name', 'contact_name', 'email', 'website_url', 'status', 'lead_temperature', 'analysis_status', 'opportunity_score', 'commercial_opportunity_score', 'prospecting_context', 'findings', 'analysis_error', 'contact_details', 'analysed_at', 'outreach_subject', 'outreach_body', 'showcase_video_url', 'showcase_video_thumbnail_url', 'approved_at', 'approved_by', 'sent_at', 'scheduled_send_at', 'next_follow_up_at', 'replied_at', 'converted_at', 'suppressed_at', 'notes'];

    protected $attributes = ['lead_temperature' => 'cold'];

    protected $casts = ['prospecting_context' => 'array', 'findings' => 'array', 'contact_details' => 'array', 'analysed_at' => 'datetime', 'approved_at' => 'datetime', 'sent_at' => 'datetime', 'scheduled_send_at' => 'datetime', 'next_follow_up_at' => 'datetime', 'replied_at' => 'datetime', 'converted_at' => 'datetime', 'suppressed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::created(function (Prospect $prospect): void {
            $prospect->outreachState()->create(ProspectOutreachState::initialAttributesFor($prospect));
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function industryProfile(): BelongsTo
    {
        return $this->belongsTo(ProspectingIndustryProfile::class, 'prospecting_industry_profile_id');
    }

    public function prospectingLocation(): BelongsTo
    {
        return $this->belongsTo(ProspectingLocation::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProspectActivity::class)->latest();
    }

    public function outreachDeliveries(): HasMany
    {
        return $this->hasMany(ProspectOutreachDelivery::class)->latest('sent_at');
    }

    public function outreachState(): HasOne
    {
        return $this->hasOne(ProspectOutreachState::class);
    }

    public function engagementEvents(): HasMany
    {
        return $this->hasMany(ProspectEngagementEvent::class)->latest('occurred_at');
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('user_id', $user->id);
    }

    public function isAccessibleBy(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $this->user_id === $user->id);
    }

    public function isOutreachFollowUpDue(): bool
    {
        return $this->sent_at !== null
            && $this->next_follow_up_at !== null
            && $this->next_follow_up_at->lessThanOrEqualTo(now());
    }

    public function recordActivity(string $type, string $description, ?User $user = null): ProspectActivity
    {
        return $this->activities()->create(['user_id' => $user?->id, 'type' => $type, 'description' => $description]);
    }
}
