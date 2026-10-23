<?php

namespace App\Models;

use Database\Factories\AgentContentTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentContentTask extends Model
{
    /** @use HasFactory<AgentContentTaskFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'content_request_id', 'user_id', 'scheduled_for', 'timezone', 'fingerprint', 'status', 'project', 'repository', 'branch', 'evidence', 'rationale', 'pull_request_url', 'result', 'blocked_reason'];

    protected $attributes = ['status' => 'in_progress'];

    protected function casts(): array
    {
        return ['scheduled_for' => 'date', 'project' => 'array', 'evidence' => 'array', 'result' => 'array'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function contentRequest(): BelongsTo
    {
        return $this->belongsTo(ContentRequest::class);
    }
}
