<?php

namespace App\Models;

use Database\Factories\ProspectDiscoveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectDiscovery extends Model
{
    /** @use HasFactory<ProspectDiscoveryFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'area', 'business_type', 'status', 'candidate_count', 'error', 'started_at', 'completed_at'];

    protected $attributes = ['status' => 'pending', 'candidate_count' => 0];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(ProspectDiscoveryCandidate::class);
    }
}
