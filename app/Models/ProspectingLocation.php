<?php

namespace App\Models;

use Database\Factories\ProspectingLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectingLocation extends Model
{
    /** @use HasFactory<ProspectingLocationFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'enabled', 'priority'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function searches(): HasMany
    {
        return $this->hasMany(SeoProspectSearch::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }
}
