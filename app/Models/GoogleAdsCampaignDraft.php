<?php

namespace App\Models;

use Database\Factories\GoogleAdsCampaignDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAdsCampaignDraft extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CREATED = 'created';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNCERTAIN = 'uncertain';

    /** @use HasFactory<GoogleAdsCampaignDraftFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'google_ads_connection_id', 'created_by', 'request_key', 'customer_id', 'name', 'daily_budget_micros', 'city_name', 'country_code', 'radius_miles', 'final_url', 'keywords', 'headlines', 'descriptions', 'status', 'campaign_resource_name', 'error'];

    protected $attributes = ['status' => self::STATUS_PENDING];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'headlines' => 'array', 'descriptions' => 'array', 'daily_budget_micros' => 'integer', 'radius_miles' => 'integer'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsConnection::class, 'google_ads_connection_id');
    }
}
