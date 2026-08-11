<?php

namespace App\Models;

use Database\Factories\SearchConsoleConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchConsoleConnection extends Model
{
    /** @use HasFactory<SearchConsoleConnectionFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'connected_by', 'property_url', 'permission_level', 'access_token', 'access_token_expires_at', 'refresh_token', 'opportunities_checked_at', 'opportunities_error'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'opportunities_checked_at' => 'datetime',
        ];
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
