<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FormSubmission extends Model
{
    public const STATUSES = ['new', 'contacted', 'qualified', 'won', 'lost'];

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
        'follow_up_at',
        'email_sent_at',
        'email_failed_at',
        'email_error',
        'autoresponder_sent_at',
        'autoresponder_failed_at',
        'autoresponder_error',
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
        'follow_up_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'email_failed_at' => 'datetime',
        'autoresponder_sent_at' => 'datetime',
        'autoresponder_failed_at' => 'datetime',
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

    public function activities(): HasMany
    {
        return $this->hasMany(FormSubmissionActivity::class)->latest();
    }

    /** @param array<string, mixed>|null $metadata */
    public function recordActivity(string $type, string $description, ?User $user = null, ?array $metadata = null): FormSubmissionActivity
    {
        return $this->activities()->create([
            'user_id' => $user?->id,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        $spam = $filters['spam'] ?? 'exclude';

        return $query
            ->when($spam === 'exclude', fn (Builder $query) => $query->where('is_spam', false))
            ->when($spam === 'only', fn (Builder $query) => $query->where('is_spam', true))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['website_id'] ?? null), fn (Builder $query) => $query->where('website_id', $filters['website_id']))
            ->when(filled($filters['assigned_to'] ?? null), fn (Builder $query) => $filters['assigned_to'] === 'unassigned'
                ? $query->whereNull('assigned_to')
                : $query->where('assigned_to', $filters['assigned_to']))
            ->when(($filters['follow_up'] ?? null) === 'overdue', fn (Builder $query) => $query->where('follow_up_at', '<', now())->whereNotIn('status', ['won', 'lost']))
            ->when(($filters['follow_up'] ?? null) === 'today', fn (Builder $query) => $query->whereBetween('follow_up_at', [today(), today()->endOfDay()])->whereNotIn('status', ['won', 'lost']))
            ->when(($filters['follow_up'] ?? null) === 'upcoming', fn (Builder $query) => $query->where('follow_up_at', '>', today()->endOfDay())->whereNotIn('status', ['won', 'lost']))
            ->when(($filters['follow_up'] ?? null) === 'none', fn (Builder $query) => $query->whereNull('follow_up_at'))
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = '%'.Str::limit(Str::of($filters['search'])->trim(), 100, '').'%';
                $query->where(fn (Builder $query) => $query->where('source_domain', 'like', $search)->orWhere('source_url', 'like', $search)->orWhere('data', 'like', $search));
            });
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

    public function contactName(): ?string
    {
        $data = $this->data ?? [];

        foreach (['name', 'full_name', 'full-name', 'your_name', 'your-name', 'contact_name'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && filled($data[$key])) {
                return trim($data[$key]);
            }
        }

        $name = trim(implode(' ', array_filter([
            is_string($data['first_name'] ?? null) ? $data['first_name'] : null,
            is_string($data['last_name'] ?? null) ? $data['last_name'] : null,
        ])));

        return $name !== '' ? $name : null;
    }

    public function displayName(): string
    {
        return $this->contactName() ?? $this->replyToEmail() ?? $this->source_domain ?? 'Unknown lead';
    }

    public function messageExcerpt(): ?string
    {
        foreach (['message', 'enquiry', 'comments', 'comment', 'details'] as $key) {
            $value = $this->data[$key] ?? null;

            if (is_string($value) && filled($value)) {
                return Str::limit(trim($value), 120);
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
