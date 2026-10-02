<?php

namespace App\Models;

use Database\Factories\GoogleAdsConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAdsConnection extends Model
{
    /** @use HasFactory<GoogleAdsConnectionFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'connected_by', 'customer_id', 'login_customer_id', 'customer_name', 'currency_code', 'access_token', 'refresh_token', 'access_token_expires_at', 'campaign_form_draft', 'campaign_form_draft_saved_at'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'access_token_expires_at' => 'datetime', 'campaign_form_draft' => 'array', 'campaign_form_draft_saved_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }
}
