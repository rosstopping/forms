<?php

namespace Database\Factories;

use App\Models\GoogleAdsTrackingRequest;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleAdsTrackingRequest>
 */
class GoogleAdsTrackingRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'website_repository_id' => WebsiteRepository::factory(),
            'requested_by' => User::factory(),
            'customer_id' => '1234567890',
            'conversion_action_id' => '456',
            'conversion_action_name' => 'Lead form submitted',
            'send_to' => 'AW-123456789/ExampleLabel',
            'lead_success_description' => 'The contact form has been accepted and saved.',
            'status' => GoogleAdsTrackingRequest::STATUS_QUEUED,
        ];
    }
}
