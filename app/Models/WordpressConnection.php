<?php

namespace App\Models;

use Database\Factories\WordpressConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WordpressConnection extends Model
{
    /** @use HasFactory<WordpressConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'website_id',
        'public_id',
        'pairing_code_hash',
        'pairing_code_expires_at',
        'credential_hash',
        'webhook_secret',
        'wordpress_url',
        'plugin_version',
        'active_release_public_id',
        'connected_at',
        'last_seen_at',
        'last_deployed_at',
        'revoked_at',
    ];

    protected $hidden = [
        'pairing_code_hash',
        'credential_hash',
        'webhook_secret',
    ];

    protected $casts = [
        'pairing_code_expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_deployed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'webhook_secret' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::creating(function (WordpressConnection $connection): void {
            $connection->public_id ??= 'wpc_'.Str::lower(Str::random(28));
        });
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function isConnected(): bool
    {
        return filled($this->credential_hash) && $this->revoked_at === null;
    }
}
