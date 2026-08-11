<?php

namespace App\Services;

use App\Models\BusinessProfileConnection;
use Illuminate\Support\Collection;

class BusinessProfileAuditor
{
    public function __construct(protected BusinessProfileClient $client) {}

    /** @return array{snapshot: array<string, mixed>, recommendations: array<int, array<string, mixed>>} */
    public function audit(BusinessProfileConnection $connection): array
    {
        $location = $this->client->location($connection);
        $this->client->syncReviews($connection);
        $recommendations = collect();

        if (blank(data_get($location, 'websiteUri')) && $domain = $connection->website->primaryDomain()?->domain) {
            $recommendations->push(['key' => 'website', 'severity' => 'warning', 'title' => 'Add the website', 'description' => 'Connect the profile to the business website.', 'field_mask' => 'websiteUri', 'current_value' => null, 'proposed_value' => ['websiteUri' => 'https://'.$domain]]);
        }
        $this->missing($recommendations, $location, 'phoneNumbers.primaryPhone', 'phone', 'Add a primary phone number', 'Make it easy for customers to call the business.');
        $this->missing($recommendations, $location, 'regularHours.periods', 'hours', 'Add regular opening hours', 'Profiles with complete hours set clearer customer expectations.');
        $this->missing($recommendations, $location, 'profile.description', 'description', 'Add a business description', 'Explain what the business offers and who it serves.');

        $unanswered = $connection->reviews()->where('reply_status', 'unanswered')->count();
        if ($unanswered > 0) {
            $recommendations->push(['key' => 'unanswered_reviews', 'severity' => 'warning', 'title' => "Reply to {$unanswered} customer review(s)", 'description' => 'Generate individual replies and approve each one before publishing.', 'field_mask' => null, 'current_value' => ['count' => $unanswered], 'proposed_value' => null]);
        }

        return ['snapshot' => $location, 'recommendations' => $recommendations->all()];
    }

    protected function missing(Collection $recommendations, array $location, string $path, string $key, string $title, string $description): void
    {
        if (blank(data_get($location, $path))) {
            $recommendations->push(compact('key', 'title', 'description') + ['severity' => 'warning', 'field_mask' => null, 'current_value' => null, 'proposed_value' => null]);
        }
    }
}
