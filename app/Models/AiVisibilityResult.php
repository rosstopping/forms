<?php

namespace App\Models;

use Database\Factories\AiVisibilityResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiVisibilityResult extends Model
{
    /** @use HasFactory<AiVisibilityResultFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'ai_visibility_prompt_id', 'provider', 'model', 'prompt_snapshot', 'prompt_fingerprint', 'identity_snapshot', 'cohort', 'period_start', 'status', 'attempts', 'started_at', 'checked_at', 'response_text', 'brand_mentioned', 'brand_position', 'website_mentioned', 'website_cited', 'citations', 'competitors', 'analysis', 'usage', 'error'];

    protected $attributes = ['status' => 'queued', 'attempts' => 0];

    protected function casts(): array
    {
        return ['identity_snapshot' => 'array', 'period_start' => 'date', 'started_at' => 'datetime', 'checked_at' => 'datetime', 'brand_mentioned' => 'boolean', 'brand_position' => 'integer', 'website_mentioned' => 'boolean', 'website_cited' => 'boolean', 'citations' => 'array', 'competitors' => 'array', 'analysis' => 'array', 'usage' => 'array'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityPrompt::class, 'ai_visibility_prompt_id')->withTrashed();
    }
}
