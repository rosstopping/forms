<?php

namespace App\Models;

use Database\Factories\FormSubmissionFollowUpReminderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmissionFollowUpReminder extends Model
{
    /** @use HasFactory<FormSubmissionFollowUpReminderFactory> */
    use HasFactory;

    protected $fillable = ['form_submission_id', 'due_at', 'status', 'recipient', 'sent_at', 'failed_at', 'error'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function cancel(string $reason): void
    {
        $this->update(['status' => 'cancelled', 'error' => $reason]);
        $this->submission?->recordActivity('follow_up_reminder_cancelled', $reason, metadata: ['reminder_id' => $this->id]);
    }
}
