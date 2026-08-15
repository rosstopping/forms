<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\MembershipPlan;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'stripe_customer_id', 'stripe_subscription_id', 'membership_tier', 'membership_status', 'membership_current_period_end', 'membership_cancel_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function hasMembershipFeature(string $feature): bool
    {
        return $this->isAdmin() || (in_array($this->membership_status, ['active', 'trialing'], true)
            && MembershipPlan::includes($this->membership_tier, $feature));
    }

    public function hasActiveMembership(): bool
    {
        return in_array($this->membership_status, ['active', 'trialing'], true);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class, 'user_id');
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
}
