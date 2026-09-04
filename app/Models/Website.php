<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Website extends Model
{
    public const MEMBER_ROLE_MANAGER = 'manager';

    public const MEMBER_ROLE_VIEWER = 'viewer';

    public const MEMBER_ROLES = [self::MEMBER_ROLE_MANAGER, self::MEMBER_ROLE_VIEWER];

    use HasFactory;

    protected $attributes = [
        'autoresponder_content_type' => 'text',
        'autoresponder_delay_minutes' => 0,
    ];

    protected $hidden = [
        'turnstile_secret_key',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'is_active',
        'auto_discovered',
        'email_enabled',
        'email_recipients',
        'autoresponder_enabled',
        'autoresponder_from_name',
        'autoresponder_from_email',
        'autoresponder_subject',
        'autoresponder_body',
        'autoresponder_content_type',
        'autoresponder_delay_minutes',
        'webhook_enabled',
        'health_reports_enabled',
        'seo_weekly_snapshots_enabled',
        'seo_history_backfilled_at',
        'webhook_url',
        'webhook_secret',
        'success_redirect_url',
        'failure_redirect_url',
        'turnstile_enabled',
        'turnstile_site_key',
        'turnstile_secret_key',
        'first_seen_at',
        'pixel_enabled',
        'copilot_build_task_id',
        'copilot_build_task_url',
        'copilot_build_task_state',
        'copilot_build_prompt',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'autoresponder_enabled' => 'boolean',
        'autoresponder_delay_minutes' => 'integer',
        'webhook_enabled' => 'boolean',
        'health_reports_enabled' => 'boolean',
        'seo_weekly_snapshots_enabled' => 'boolean',
        'seo_history_backfilled_at' => 'datetime',
        'turnstile_enabled' => 'boolean',
        'auto_discovered' => 'boolean',
        'is_active' => 'boolean',
        'email_recipients' => 'array',
        'first_seen_at' => 'datetime',
        'pixel_last_seen_at' => 'datetime',
        'pixel_enabled' => 'boolean',
        'pixel_payload_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Website $website): void {
            $website->pixel_public_key ??= 'sw_'.Str::lower(Str::random(28));
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query
            ->where('user_id', $user->id)
            ->orWhereHas('members', fn (Builder $query) => $query->whereKey($user->id)));
    }

    public function scopeManageableBy(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query
            ->where('user_id', $user->id)
            ->orWhereHas('members', fn (Builder $query) => $query
                ->whereKey($user->id)
                ->where('user_website.role', self::MEMBER_ROLE_MANAGER)));
    }

    public function isAccessibleBy(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $this->user_id === $user->id || $this->members()->whereKey($user->id)->exists());
    }

    public function isManageableBy(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $this->user_id === $user->id || $this->members()->whereKey($user->id)->wherePivot('role', self::MEMBER_ROLE_MANAGER)->exists());
    }

    public function membershipRoleFor(User $user): ?string
    {
        if ($this->user_id === $user->id) {
            return 'owner';
        }

        return $this->members()->whereKey($user->id)->first()?->pivot?->role;
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

    public function mailConnection(): HasOne
    {
        return $this->hasOne(WebsiteMailConnection::class);
    }

    public function healthReports(): HasMany
    {
        return $this->hasMany(WebsiteHealthReport::class);
    }

    public function latestHealthReport(): HasOne
    {
        return $this->hasOne(WebsiteHealthReport::class)->latestOfMany();
    }

    public function repository(): HasOne
    {
        return $this->hasOne(WebsiteRepository::class);
    }

    public function wordpressConnection(): HasOne
    {
        return $this->hasOne(WordpressConnection::class);
    }

    public function wordpressStaticReleases(): HasMany
    {
        return $this->hasMany(WordpressStaticRelease::class);
    }

    public function searchConsoleConnection(): HasOne
    {
        return $this->hasOne(SearchConsoleConnection::class);
    }

    public function businessProfileConnection(): HasOne
    {
        return $this->hasOne(BusinessProfileConnection::class);
    }

    public function contentPlan(): HasOne
    {
        return $this->hasOne(ContentPlan::class);
    }

    public function contentRequests(): HasMany
    {
        return $this->hasMany(ContentRequest::class);
    }

    public function searchOpportunities(): HasMany
    {
        return $this->hasMany(SearchOpportunity::class);
    }

    public function seoSnapshots(): HasMany
    {
        return $this->hasMany(SeoSnapshot::class);
    }

    public function latestSeoSnapshot(): HasOne
    {
        return $this->hasOne(SeoSnapshot::class)->latestOfMany('snapshot_date');
    }

    public function seoKeywords(): HasMany
    {
        return $this->hasMany(SeoKeyword::class);
    }

    public function seoReferringDomains(): HasMany
    {
        return $this->hasMany(SeoReferringDomain::class);
    }

    public function seoCompetitors(): HasMany
    {
        return $this->hasMany(SeoCompetitor::class);
    }

    public function seoOpportunities(): HasMany
    {
        return $this->hasMany(SeoOpportunity::class);
    }

    public function optimisations(): HasMany
    {
        return $this->hasMany(Optimisation::class);
    }

    public function pixelPages(): HasMany
    {
        return $this->hasMany(PixelPageSighting::class);
    }

    public function outreachProspect(): HasOne
    {
        return $this->hasOne(Prospect::class);
    }

    public function externalApiUsages(): HasMany
    {
        return $this->hasMany(ExternalApiUsage::class);
    }

    public function aiQuestions(): HasMany
    {
        return $this->hasMany(WebsiteAiQuestion::class);
    }

    public function primaryDomain(): ?WebsiteDomain
    {
        return $this->domains()->where('is_primary', true)->first() ?: $this->domains()->first();
    }
}
