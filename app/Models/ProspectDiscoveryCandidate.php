<?php

namespace App\Models;

use Database\Factories\ProspectDiscoveryCandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectDiscoveryCandidate extends Model
{
    /** @use HasFactory<ProspectDiscoveryCandidateFactory> */
    use HasFactory;

    protected $fillable = ['prospect_discovery_id', 'prospect_id', 'source_key', 'business_name', 'website_url', 'phone', 'address', 'source_data', 'status'];

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return ['source_data' => 'array'];
    }

    public function discovery(): BelongsTo
    {
        return $this->belongsTo(ProspectDiscovery::class, 'prospect_discovery_id');
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
