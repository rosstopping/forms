<?php

namespace App\Models;

use App\Enums\DeploymentMethod;
use App\Enums\OptimisationStatus;
use App\Enums\OptimisationType;
use App\Services\PixelUrlNormalizer;
use Database\Factories\OptimisationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Optimisation extends Model
{
    /** @use HasFactory<OptimisationFactory> */
    use HasFactory;

    protected $fillable = [
        'website_id',
        'website_health_report_page_id',
        'content_request_id',
        'url',
        'type',
        'selector',
        'target_description',
        'attribute',
        'status',
        'deployment_method',
        'approved_at',
        'deployed_at',
        'rolled_back_at',
    ];

    protected $attributes = [
        'status' => OptimisationStatus::Draft->value,
        'deployment_method' => DeploymentMethod::Pixel->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (Optimisation $optimisation): void {
            $optimisation->public_id ??= 'opt_'.Str::lower(Str::random(24));
            $optimisation->url_hash = app(PixelUrlNormalizer::class)->hash($optimisation->url);
        });

        static::updating(function (Optimisation $optimisation): void {
            if ($optimisation->isDirty('url')) {
                $optimisation->url_hash = app(PixelUrlNormalizer::class)->hash($optimisation->url);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => OptimisationType::class,
            'status' => OptimisationStatus::class,
            'deployment_method' => DeploymentMethod::class,
            'approved_at' => 'datetime',
            'deployed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(WebsiteHealthReportPage::class, 'website_health_report_page_id');
    }

    public function contentRequest(): BelongsTo
    {
        return $this->belongsTo(ContentRequest::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(OptimisationVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(OptimisationVersion::class)->ofMany('version', 'max');
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(OptimisationDeployment::class);
    }
}
