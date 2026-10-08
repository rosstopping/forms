<?php

namespace App\Models;

use Database\Factories\MarketingConversionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_id', 'deduplication_key', 'name', 'attribution'])]
class MarketingConversion extends Model
{
    /** @use HasFactory<MarketingConversionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['attribution' => 'array'];
    }

    /** @return array{event: string, event_id: string, is_conversion: bool, attribution: array<string, mixed>} */
    public function payload(): array
    {
        return [
            'event' => $this->name,
            'event_id' => $this->event_id,
            'is_conversion' => in_array($this->name, ['lead_captured', 'call_booked'], true),
            'attribution' => $this->attribution ?? [],
        ];
    }
}
