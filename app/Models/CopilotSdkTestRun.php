<?php

namespace App\Models;

use Database\Factories\CopilotSdkTestRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopilotSdkTestRun extends Model
{
    /** @use HasFactory<CopilotSdkTestRunFactory> */
    use HasFactory;

    protected $fillable = [
        'run_id', 'website_repository_id', 'requested_by', 'repository_id', 'installation_id',
        'full_name', 'base_branch', 'base_sha', 'tree_sha', 'path', 'title', 'original', 'replacement',
        'branch', 'commit_sha', 'status', 'usage', 'error', 'pull_request_url',
    ];

    protected $hidden = ['original', 'replacement'];

    protected function casts(): array
    {
        return ['original' => 'encrypted', 'replacement' => 'encrypted', 'usage' => 'array'];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(WebsiteRepository::class, 'website_repository_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
