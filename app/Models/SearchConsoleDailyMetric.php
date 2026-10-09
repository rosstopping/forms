<?php

namespace App\Models;

use Database\Factories\SearchConsoleDailyMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchConsoleDailyMetric extends Model
{
    /** @use HasFactory<SearchConsoleDailyMetricFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'search_console_connection_id', 'property_url', 'property_hash', 'date', 'data_status', 'clicks', 'impressions', 'position', 'fetched_at'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'clicks' => 'integer', 'impressions' => 'integer', 'position' => 'float', 'fetched_at' => 'immutable_datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
