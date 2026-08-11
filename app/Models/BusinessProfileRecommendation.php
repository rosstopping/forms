<?php

namespace App\Models;

use Database\Factories\BusinessProfileRecommendationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessProfileRecommendation extends Model
{
    /** @use HasFactory<BusinessProfileRecommendationFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLYING = 'applying';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = ['business_profile_audit_id', 'key', 'severity', 'title', 'description', 'field_mask', 'current_value', 'proposed_value', 'status', 'approved_by', 'approved_at', 'applied_at', 'error'];

    protected function casts(): array
    {
        return ['current_value' => 'array', 'proposed_value' => 'array', 'approved_at' => 'datetime', 'applied_at' => 'datetime'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(BusinessProfileAudit::class, 'business_profile_audit_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
