<?php

namespace App\Models;

use Database\Factories\AiVisibilitySettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiVisibilitySetting extends Model
{
    /** @use HasFactory<AiVisibilitySettingFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'enabled', 'providers', 'frequency_days', 'brand_name', 'aliases', 'services', 'locations'];

    protected $attributes = ['enabled' => false, 'frequency_days' => 7];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'providers' => 'array', 'frequency_days' => 'integer', 'aliases' => 'array', 'services' => 'array', 'locations' => 'array'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
