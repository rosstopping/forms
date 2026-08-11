<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'is_active',
        'auto_discovered',
        'email_enabled',
        'email_recipients',
        'webhook_enabled',
        'health_reports_enabled',
        'webhook_url',
        'webhook_secret',
        'success_redirect_url',
        'failure_redirect_url',
        'turnstile_enabled',
        'turnstile_secret_key',
        'first_seen_at',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'webhook_enabled' => 'boolean',
        'health_reports_enabled' => 'boolean',
        'turnstile_enabled' => 'boolean',
        'auto_discovered' => 'boolean',
        'is_active' => 'boolean',
        'email_recipients' => 'array',
        'first_seen_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(WebsiteDomain::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function healthReports(): HasMany
    {
        return $this->hasMany(WebsiteHealthReport::class);
    }

    public function repository(): HasOne
    {
        return $this->hasOne(WebsiteRepository::class);
    }

    public function searchConsoleConnection(): HasOne
    {
        return $this->hasOne(SearchConsoleConnection::class);
    }

    public function contentPlan(): HasOne
    {
        return $this->hasOne(ContentPlan::class);
    }

    public function primaryDomain(): ?WebsiteDomain
    {
        return $this->domains()->where('is_primary', true)->first() ?: $this->domains()->first();
    }
}
