<?php

namespace App\Models;

use Database\Factories\BusinessProfileAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessProfileAudit extends Model
{
    /** @use HasFactory<BusinessProfileAuditFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['business_profile_connection_id', 'status', 'overall_status', 'snapshot', 'error', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(BusinessProfileConnection::class, 'business_profile_connection_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(BusinessProfileRecommendation::class);
    }
}
