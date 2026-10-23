<?php

namespace App\Models;

use Database\Factories\WebsiteLocalProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteLocalProject extends Model
{
    /** @use HasFactory<WebsiteLocalProjectFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'host', 'path', 'codex_project_id'];

    protected $attributes = ['host' => 'local'];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
