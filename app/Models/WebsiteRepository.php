<?php

namespace App\Models;

use Database\Factories\WebsiteRepositoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteRepository extends Model
{
    /** @use HasFactory<WebsiteRepositoryFactory> */
    use HasFactory;

    protected $fillable = [
        'website_id',
        'github_installation_id',
        'repository_id',
        'full_name',
        'default_branch',
        'private',
        'permissions',
        'project_path',
    ];

    protected function casts(): array
    {
        return [
            'private' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(GithubInstallation::class, 'github_installation_id');
    }

    public function remediationRuns(): HasMany
    {
        return $this->hasMany(RemediationRun::class);
    }

    public function contentGenerations(): HasMany
    {
        return $this->hasMany(ContentGeneration::class);
    }
}
