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

    protected $fillable = ['website_id', 'created_by', 'enabled', 'additional_weekdays', 'weekday', 'hour', 'timezone', 'audience', 'guidance', 'last_generated_at', 'suggestion_reminder_sent_for'];

    protected $attributes = ['enabled' => false, 'weekday' => 1, 'hour' => 8, 'timezone' => 'Europe/London'];

    protected function casts(): array
    {
        return ['additional_weekdays' => 'array', 'weekday' => 'integer', 'hour' => 'integer', 'enabled' => 'boolean', 'last_generated_at' => 'datetime', 'suggestion_reminder_sent_for' => 'datetime'];
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
