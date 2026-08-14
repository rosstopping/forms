<?php

namespace App\Models;

use Database\Factories\SearchConsoleMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchConsoleMetric extends Model
{
    public const SITE_DIMENSION_KEY = 'site';

    /** @use HasFactory<SearchConsoleMetricFactory> */
    use HasFactory;

    protected $fillable = [
        'website_id', 'search_console_connection_id', 'property_url', 'property_hash', 'month', 'dimension_key',
        'query', 'clicks', 'impressions', 'ctr', 'position',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'clicks' => 'float',
            'impressions' => 'float',
            'ctr' => 'float',
            'position' => 'float',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SearchConsoleConnection::class, 'search_console_connection_id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
