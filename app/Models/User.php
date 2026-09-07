<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\MembershipPlan;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lab404\Impersonate\Models\Impersonate;

#[Fillable(['name', 'email', 'password', 'role', 'current_website_id', 'stripe_customer_id', 'stripe_subscription_id', 'membership_tier', 'admin_membership_tier', 'membership_status', 'membership_current_period_end', 'membership_cancel_at', 'onboarding_status', 'onboarding_trial_ends_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Impersonate, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
            'membership_current_period_end' => 'datetime',
            'membership_cancel_at' => 'datetime',
            'onboarding_trial_ends_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function canImpersonate(): bool
    {
        return $this->isAdmin();
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->isAdmin();
    }

    public function hasMembershipFeature(string $feature): bool
    {
        return $this->isAdmin() || ($this->hasActiveMembership()
            && MembershipPlan::includes($this->effectiveMembershipTier(), $feature));
    }

    public function hasActiveMembership(): bool
    {
        if ($this->hasAdminManagedMembership() || $this->membership_status === 'active') {
            return true;
        }

        return $this->membership_status === 'trialing'
            && $this->membership_current_period_end?->isFuture() === true;
    }

    public function hasAdminManagedMembership(): bool
    {
        return $this->admin_membership_tier !== null;
    }

    public function effectiveMembershipTier(): ?string
    {
        return $this->admin_membership_tier ?? $this->membership_tier;
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class, 'user_id');
    }

    public function currentWebsite(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'current_website_id');
    }

    public function sharedWebsites(): BelongsToMany
    {
        return $this->belongsToMany(Website::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function githubAuthorization(): HasOne
    {
        return $this->hasOne(GithubUserAuthorization::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }

    public function prospectDiscoveries(): HasMany
    {
        return $this->hasMany(ProspectDiscovery::class);
    }

    public function seoProspectSearches(): HasMany
    {
        return $this->hasMany(SeoProspectSearch::class);
    }

    public function websiteAiQuestions(): HasMany
    {
        return $this->hasMany(WebsiteAiQuestion::class);
    }
}
