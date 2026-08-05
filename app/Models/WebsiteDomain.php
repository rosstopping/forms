<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteDomain extends Model
{
    protected $fillable = ['website_id', 'domain', 'is_primary'];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
