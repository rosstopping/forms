<?php

namespace App\Models;

use Database\Factories\GithubInstallationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GithubInstallation extends Model
{
    /** @use HasFactory<GithubInstallationFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'installed_by',
        'installation_id',
        'account_id',
        'account_login',
        'account_type',
        'repository_selection',
        'permissions',
        'status',
        'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'suspended_at' => 'datetime',
        ];
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(WebsiteRepository::class);
    }
}
