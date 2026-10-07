<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['website_audit_id', 'visit_id', 'active_seconds', 'scroll_percent', 'review_opened_at', 'review_dismissed_at', 'email_started_at', 'email_submit_attempted_at', 'email_submitted_at', 'booking_clicked_at', 'calendar_opened_at'])]
class WebsiteAuditVisit extends Model
{
    use HasFactory;

    public const EVENTS = ['viewed', 'heartbeat', 'review_opened', 'review_dismissed', 'email_started', 'email_submit_attempted', 'booking_clicked', 'calendar_opened'];

    protected $attributes = ['active_seconds' => 0, 'scroll_percent' => 0];

    protected function casts(): array
    {
        return ['active_seconds' => 'integer', 'scroll_percent' => 'integer', 'review_opened_at' => 'datetime', 'review_dismissed_at' => 'datetime', 'email_started_at' => 'datetime', 'email_submit_attempted_at' => 'datetime', 'email_submitted_at' => 'datetime', 'booking_clicked_at' => 'datetime', 'calendar_opened_at' => 'datetime'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(WebsiteAudit::class, 'website_audit_id');
    }
}
