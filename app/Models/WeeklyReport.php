<?php

namespace App\Models;

use Database\Factories\WeeklyReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyReport extends Model
{
    /** @use HasFactory<WeeklyReportFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'period_start', 'period_end', 'snapshot', 'overview', 'recommended_priority', 'narrative_source', 'generated_at'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'snapshot' => 'array', 'recommended_priority' => 'array', 'generated_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
