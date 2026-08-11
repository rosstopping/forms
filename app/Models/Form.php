<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'name',
        'slug',
        'is_active',
        'auto_discovered',
        'email_enabled_override',
        'email_recipients_override',
        'email_subject_override',
        'autoresponder_enabled_override',
        'autoresponder_subject_override',
        'autoresponder_body_override',
        'webhook_enabled_override',
        'webhook_url_override',
        'webhook_secret_override',
        'success_redirect_url_override',
        'failure_redirect_url_override',
        'first_seen_at',
        'last_submission_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_discovered' => 'boolean',
        'email_enabled_override' => 'boolean',
        'autoresponder_enabled_override' => 'boolean',
        'webhook_enabled_override' => 'boolean',
        'email_recipients_override' => 'array',
        'first_seen_at' => 'datetime',
        'last_submission_at' => 'datetime',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }
}
