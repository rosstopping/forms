<?php

namespace App\Models;

use Database\Factories\FormSubmissionEmailDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmissionEmailDelivery extends Model
{
    /** @use HasFactory<FormSubmissionEmailDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'form_submission_id',
        'website_id',
        'website_mail_connection_id',
        'type',
        'mode',
        'status',
        'recipient',
        'subject',
        'from_email',
        'from_name',
        'provider_message_id',
        'suppression_reason',
        'failure_reason',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    protected $attributes = [
        'type' => 'autoresponder',
        'mode' => 'legacy',
        'status' => 'queued',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WebsiteMailConnection::class, 'website_mail_connection_id');
    }
}
