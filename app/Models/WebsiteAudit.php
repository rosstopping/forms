<?php

namespace App\Models;

use Database\Factories\WebsiteAuditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['lead_call_booking_uid', 'customer_goal', 'personal_review_requested_at', 'personal_review_due_at', 'personal_review_queued_at', 'personal_review', 'marketing_consent_at', 'marketing_consent_withdrawn_at', 'marketing_consent_version', 'lead_replied_at', 'lead_call_booked_at', 'lead_converted_at', 'marketing_attribution', 'user_id', 'website_id', 'website_url', 'domain', 'email', 'status', 'opportunity_score', 'findings', 'insights', 'contact_details', 'analysis_error', 'started_at', 'completed_at', 'claim_email_sent_at', 'report_requested_at', 'claimed_at', 'expires_at'])]
class WebsiteAudit extends Model
{
    /** @use HasFactory<WebsiteAuditFactory> */
    use HasFactory;

    public const MARKETING_CONSENT_VERSION = 'website-advice-v1';

    public const MARKETING_CONSENT_TEXT = 'Email me practical advice and follow-up about improving my website. Unsubscribe any time.';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $attributes = ['status' => self::STATUS_PENDING];

    protected static function booted(): void
    {
        static::creating(function (WebsiteAudit $audit): void {
            $audit->public_id ??= 'wa_'.Str::lower(Str::random(30));
        });
    }

    protected function casts(): array
    {
        return [
            'personal_review' => 'array',
            'personal_review_requested_at' => 'datetime',
            'personal_review_due_at' => 'datetime',
            'personal_review_queued_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
            'marketing_consent_withdrawn_at' => 'datetime',
            'lead_replied_at' => 'datetime',
            'lead_call_booked_at' => 'datetime',
            'lead_converted_at' => 'datetime',
            'findings' => 'array',
            'insights' => 'array',
            'marketing_attribution' => 'array',
            'contact_details' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'claim_email_sent_at' => 'datetime',
            'report_requested_at' => 'datetime',
            'claimed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function visits(): HasMany
    {
        return $this->hasMany(WebsiteAuditVisit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function isReadyToDisplay(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->created_at->addSeconds(10)->isPast();
    }
}
