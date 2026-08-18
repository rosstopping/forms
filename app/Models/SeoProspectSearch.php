<?php

namespace App\Models;

use Database\Factories\SeoProspectSearchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoProspectSearch extends Model
{
    /** @use HasFactory<SeoProspectSearchFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'industry', 'location', 'service_keywords', 'keywords', 'minimum_position', 'maximum_position', 'maximum_pages', 'provider', 'status', 'candidate_count', 'suitable_count', 'imported_count', 'api_cost', 'error', 'started_at', 'completed_at'];

    protected $attributes = ['minimum_position' => 20, 'maximum_position' => 100, 'maximum_pages' => 20, 'provider' => 'dataforseo', 'status' => 'pending', 'candidate_count' => 0, 'suitable_count' => 0, 'imported_count' => 0, 'api_cost' => 0];

    protected function casts(): array
    {
        return ['service_keywords' => 'array', 'keywords' => 'array', 'api_cost' => 'decimal:6', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(SeoProspectCandidate::class);
    }
}
