<?php

namespace App\Models;

use Database\Factories\WebsiteMailConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteMailConnection extends Model
{
    /** @use HasFactory<WebsiteMailConnectionFactory> */
    use HasFactory;

    public const MODE_LEGACY = 'legacy';

    public const MODE_MANAGED = 'managed';

    public const MODE_CUSTOMER_POSTMARK = 'customer_postmark';

    public const MODES = [self::MODE_LEGACY, self::MODE_MANAGED, self::MODE_CUSTOMER_POSTMARK];

    protected $fillable = [
        'website_id',
        'mode',
        'status',
        'postmark_server_token',
        'postmark_server_id',
        'postmark_domain_id',
        'sending_domain',
        'dkim_host',
        'dkim_value',
        'dkim_verified',
        'return_path_domain',
        'return_path_cname_value',
        'return_path_verified',
        'verification_checked_at',
        'connected_at',
        'paused_at',
        'pause_reason',
        'daily_limit_override',
        'monthly_limit_override',
    ];

    protected $hidden = ['postmark_server_token'];

    protected $attributes = [
        'mode' => self::MODE_LEGACY,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'postmark_server_token' => 'encrypted',
            'connected_at' => 'datetime',
            'dkim_verified' => 'boolean',
            'return_path_verified' => 'boolean',
            'verification_checked_at' => 'datetime',
            'paused_at' => 'datetime',
            'daily_limit_override' => 'integer',
            'monthly_limit_override' => 'integer',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(FormSubmissionEmailDelivery::class);
    }
}
