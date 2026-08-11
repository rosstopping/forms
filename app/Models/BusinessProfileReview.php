<?php

namespace App\Models;

use Database\Factories\BusinessProfileReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessProfileReview extends Model
{
    /** @use HasFactory<BusinessProfileReviewFactory> */
    use HasFactory;

    public const STATUS_UNANSWERED = 'unanswered';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_REPLIED = 'replied';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['business_profile_connection_id', 'google_review_name', 'reviewer_name', 'star_rating', 'comment', 'reviewed_at', 'google_reply', 'suggested_reply', 'reply_status', 'approved_by', 'approved_at', 'replied_at', 'error'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'replied_at' => 'datetime'];
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
