<?php

namespace App\Models;

use Database\Factories\ProspectingIndustryProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ProspectingIndustryProfile extends Model
{
    /** @use HasFactory<ProspectingIndustryProfileFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'enabled', 'priority', 'estimated_customer_value', 'customer_value_band', 'service_keywords', 'search_keywords', 'minimum_position', 'maximum_position', 'maximum_site_size', 'automatic_import_score', 'notes'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'service_keywords' => 'array',
            'search_keywords' => 'array',
        ];
    }

    public function searches(): HasMany
    {
        return $this->hasMany(SeoProspectSearch::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }

    public function engagementEvents(): HasManyThrough
    {
        return $this->hasManyThrough(ProspectEngagementEvent::class, Prospect::class);
    }
}
