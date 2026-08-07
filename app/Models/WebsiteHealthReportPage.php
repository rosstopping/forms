<?php

namespace App\Models;

use Database\Factories\WebsiteHealthReportPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteHealthReportPage extends Model
{
    /** @use HasFactory<WebsiteHealthReportPageFactory> */
    use HasFactory;

    protected $fillable = [
        'url',
        'url_hash',
        'depth',
        'status_code',
        'response_time_ms',
        'title',
        'meta_description',
        'h1_count',
        'canonical_url',
        'is_indexable',
        'word_count',
        'internal_links_count',
        'images_count',
        'missing_alt_count',
        'checks',
    ];

    protected function casts(): array
    {
        return [
            'is_indexable' => 'boolean',
            'checks' => 'array',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(WebsiteHealthReport::class, 'website_health_report_id');
    }
}
