<?php

namespace Database\Factories;

use App\Models\ProspectOutreachDelivery;
use App\Models\ProspectOutreachLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectOutreachLink>
 */
class ProspectOutreachLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prospect_outreach_delivery_id' => ProspectOutreachDelivery::factory(),
            'kind' => 'book_call',
            'label' => 'Book a call',
            'destination_url' => 'https://cal.com/ross',
        ];
    }
}
