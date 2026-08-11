<?php

namespace App\Models;

use Database\Factories\BusinessProfileConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessProfileConnection extends Model
{
    /** @use HasFactory<BusinessProfileConnectionFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'connected_by', 'account_name', 'location_name', 'location_title', 'access_token', 'refresh_token', 'access_token_expires_at', 'weekly_audits_enabled', 'weekly_posts_enabled', 'post_weekday', 'post_hour', 'timezone', 'brand_guidance', 'last_synced_at'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'access_token_expires_at' => 'datetime', 'weekly_audits_enabled' => 'boolean', 'weekly_posts_enabled' => 'boolean', 'last_synced_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(BusinessProfileAudit::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BusinessProfilePost::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(BusinessProfileReview::class);
    }
}
