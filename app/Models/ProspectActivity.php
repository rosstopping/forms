<?php

namespace App\Models;

use Database\Factories\ProspectActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectActivity extends Model
{
    /** @use HasFactory<ProspectActivityFactory> */
    use HasFactory;

    protected $fillable = ['prospect_id', 'user_id', 'type', 'description', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
