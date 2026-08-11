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

    protected $fillable = ['website_id', 'created_by', 'enabled', 'weekday', 'hour', 'timezone', 'audience', 'guidance', 'last_generated_at'];

    protected $attributes = ['enabled' => false, 'weekday' => 1, 'hour' => 8, 'timezone' => 'Europe/London'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'last_generated_at' => 'datetime'];
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
