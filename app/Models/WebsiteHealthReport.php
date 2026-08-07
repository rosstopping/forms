<?php

namespace App\Models;

use Database\Factories\WebsiteHealthReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteHealthReport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @use HasFactory<WebsiteHealthReportFactory> */
    use HasFactory;

    protected $fillable = [
        'website_id',
        'status',
        'overall_status',
        'passed_checks',
        'warning_checks',
        'failed_checks',
        'categories',
        'checks',
        'metrics',
        'error',
        'started_at',
        'completed_at',
        'emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'checks' => 'array',
            'metrics' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(WebsiteHealthReportPage::class);
    }
}
