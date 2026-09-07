<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteDomain extends Model
{
    public const OWNERSHIP_PENDING = 'pending';

    public const OWNERSHIP_VERIFIED = 'verified';

    public const OWNERSHIP_CONFLICT = 'conflict';

    protected $fillable = ['website_id', 'domain', 'is_primary', 'ownership_status', 'verified_domain', 'verified_at', 'verification_method'];

    protected $attributes = ['ownership_status' => self::OWNERSHIP_VERIFIED];

    protected static function booted(): void
    {
        static::saving(function (WebsiteDomain $websiteDomain): void {
            if ($websiteDomain->ownership_status !== self::OWNERSHIP_VERIFIED) {
                $websiteDomain->verified_domain = null;
                $websiteDomain->verified_at = null;

                return;
            }

            $websiteDomain->verified_domain = self::canonicalDomain($websiteDomain->domain);
            $websiteDomain->verified_at ??= now();
            $websiteDomain->verification_method ??= 'manual';
        });
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public static function canonicalDomain(string $domain): string
    {
        return str_starts_with($domain, 'www.') ? substr($domain, 4) : $domain;
    }

    public function isVerified(): bool
    {
        return $this->ownership_status === self::OWNERSHIP_VERIFIED;
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
