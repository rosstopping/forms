<?php

namespace App\Models;

use Database\Factories\ContentPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPlan extends Model
{
    /** @use HasFactory<ContentPlanFactory> */
    use HasFactory;

    protected $fillable = ['keyword_research_enabled', 'keyword_researched_at', 'keyword_research', 'article_path', 'trend_research_enabled', 'trend_researched_at', 'trend_research', 'monthly_article_limit', 'monthly_optimisation_limit', 'monthly_copilot_limit', 'discovery_enabled', 'content_mode', 'website_id', 'created_by', 'enabled', 'additional_weekdays', 'weekday', 'hour', 'timezone', 'audience', 'guidance', 'last_generated_at', 'suggestion_reminder_sent_for', 'competitor_research_mode', 'competitor_researched_at'];

    protected $attributes = ['keyword_research_enabled' => false, 'content_mode' => 'balanced', 'discovery_enabled' => false, 'trend_research_enabled' => false, 'enabled' => false, 'weekday' => 1, 'hour' => 8, 'timezone' => 'Europe/London', 'competitor_research_mode' => 'manual'];

    protected function casts(): array
    {
        return ['keyword_research_enabled' => 'boolean', 'keyword_researched_at' => 'datetime', 'keyword_research' => 'array', 'trend_research_enabled' => 'boolean', 'trend_researched_at' => 'datetime', 'trend_research' => 'array', 'monthly_article_limit' => 'integer', 'monthly_optimisation_limit' => 'integer', 'monthly_copilot_limit' => 'integer', 'discovery_enabled' => 'boolean', 'additional_weekdays' => 'array', 'weekday' => 'integer', 'hour' => 'integer', 'enabled' => 'boolean', 'last_generated_at' => 'datetime', 'suggestion_reminder_sent_for' => 'datetime', 'competitor_researched_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generations(): HasMany
    {
        return $this->hasMany(ContentGeneration::class);
    }
}
