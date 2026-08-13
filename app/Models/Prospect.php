<?php

namespace App\Models;

use Database\Factories\ProspectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prospect extends Model
{
    /** @use HasFactory<ProspectFactory> */
    use HasFactory;

    public const STATUSES = ['new', 'researched', 'drafted', 'approved', 'contacted', 'replied', 'converted', 'not_interested'];

    protected $fillable = ['user_id', 'business_name', 'contact_name', 'email', 'website_url', 'status', 'analysis_status', 'opportunity_score', 'findings', 'analysis_error', 'contact_details', 'analysed_at', 'outreach_subject', 'outreach_body', 'showcase_video_url', 'showcase_video_thumbnail_url', 'approved_at', 'approved_by', 'sent_at', 'next_follow_up_at', 'replied_at', 'converted_at', 'suppressed_at', 'notes'];

    protected $casts = ['findings' => 'array', 'contact_details' => 'array', 'analysed_at' => 'datetime', 'approved_at' => 'datetime', 'sent_at' => 'datetime', 'next_follow_up_at' => 'datetime', 'replied_at' => 'datetime', 'converted_at' => 'datetime', 'suppressed_at' => 'datetime'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProspectActivity::class)->latest();
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('user_id', $user->id);
    }

    public function isAccessibleBy(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $this->user_id === $user->id);
    }

    public function recordActivity(string $type, string $description, ?User $user = null): ProspectActivity
    {
        return $this->activities()->create(['user_id' => $user?->id, 'type' => $type, 'description' => $description]);
    }
}
