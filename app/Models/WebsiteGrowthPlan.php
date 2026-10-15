<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class WebsiteGrowthPlan extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $attributes = ['status' => self::STATUS_COMPLETED];

    protected $fillable = ['status', 'generation_error', 'started_at', 'completed_at', 'website_id', 'created_by', 'brief', 'report', 'published_at', 'email_queued_at', 'email_recipient'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime', 'brief' => 'array', 'report' => 'array', 'published_at' => 'datetime', 'email_queued_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->published_at !== null;
    }

    public function isGenerating(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function shareUrl(): string
    {
        return URL::temporarySignedRoute('website-growth-plans.report', now()->addDays(30), $this);
    }
}
