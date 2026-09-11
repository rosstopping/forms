<?php

namespace App\Models;

use Database\Factories\ReviewInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewInvitation extends Model
{
    /** @use HasFactory<ReviewInvitationFactory> */
    use HasFactory;

    protected $fillable = ['form_submission_id', 'website_id', 'requested_by', 'status', 'recipient', 'subject', 'from_email', 'from_name', 'body', 'review_url', 'started_at', 'sent_at', 'failed_at', 'cancelled_at', 'error'];

    protected $attributes = ['status' => 'queued'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
