<?php

namespace App\Models;

use Database\Factories\GithubUserAuthorizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GithubUserAuthorization extends Model
{
    /** @use HasFactory<GithubUserAuthorizationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'github_user_id',
        'github_login',
        'access_token',
        'access_token_expires_at',
        'refresh_token',
        'refresh_token_expires_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
