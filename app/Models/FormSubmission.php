<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'form_id',
        'source_url',
        'source_domain',
        'data',
        'ip_address',
        'user_agent',
        'is_spam',
        'status',
        'notes',
        'assigned_to',
        'email_sent_at',
        'email_failed_at',
        'email_error',
        'webhook_sent_at',
        'webhook_failed_at',
        'webhook_status_code',
        'webhook_response',
        'webhook_error',
    ];

    protected $casts = [
        'data' => 'array',
        'is_spam' => 'boolean',
        'status' => 'string',
        'email_sent_at' => 'datetime',
        'email_failed_at' => 'datetime',
        'webhook_sent_at' => 'datetime',
        'webhook_failed_at' => 'datetime',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replyToEmail(): ?string
    {
        foreach ($this->data ?? [] as $key => $value) {
            $candidate = is_string($value) ? $value : null;
            if (! $candidate || ! filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $normalizedKey = Str::lower($key);

            if (in_array($normalizedKey, ['email', 'email_address', 'email-address', 'your_email', 'your-email', 'contact_email', 'contact-email'], true)) {
                return $candidate;
            }
        }

        return null;
    }

    public function resolvedStatusLabel(): string
    {
        return match ($this->status) {
            'contacted' => 'Contacted',
            'qualified' => 'Qualified',
            'won' => 'Won',
            'lost' => 'Lost',
            default => 'New',
        };
    }

    public function resolvedEmailSubject(): string
    {
        $form = $this->form;
        $website = $this->website;
        $subject = 'New '.$form?->name.' submission from '.$website?->primaryDomain()?->domain ?? $website?->name;

        if ($form && $form->email_subject_override) {
            return strtr($form->email_subject_override, [
                '{form_name}' => $form->name,
                '{website_name}' => $website?->name ?? 'Website',
                '{website_domain}' => $website?->primaryDomain()?->domain ?? $website?->name ?? 'website',
                '{submission_id}' => (string) $this->id,
            ]);
        }

        return $subject;
    }
}
