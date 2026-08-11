<?php

namespace App\Models;

use Database\Factories\BusinessProfilePostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessProfilePost extends Model
{
    /** @use HasFactory<BusinessProfilePostFactory> */
    use HasFactory;

    public const STATUS_GENERATING = 'generating';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['business_profile_connection_id', 'status', 'topic', 'summary', 'call_to_action_type', 'call_to_action_url', 'google_post_name', 'approved_by', 'approved_at', 'published_at', 'error'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(BusinessProfileConnection::class, 'business_profile_connection_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
