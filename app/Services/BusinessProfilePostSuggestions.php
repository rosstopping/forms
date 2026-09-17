<?php

namespace App\Services;

use App\Models\BusinessProfileAudit;
use App\Models\BusinessProfileConnection;

class BusinessProfilePostSuggestions
{
    /** @return array<string, array{title: string, description: string, topic: string}> */
    public function forConnection(BusinessProfileConnection $connection): array
    {
        $snapshot = $this->context($connection);
        $business = $connection->location_title ?: $connection->website->name;
        $ideas = [
            'introduction' => [
                'title' => 'Introduce your business',
                'description' => 'Help new customers get to know '.$business.'.',
                'topic' => 'Write a welcoming introduction to '.$business.'. Use only the supplied business facts; do not invent services or claims.',
            ],
        ];
        if ($category = data_get($snapshot, 'categories.primaryCategory.displayName')) {
            $ideas['category'] = [
                'title' => 'Spotlight what you do',
                'description' => 'Create a post around your Google category: '.$category.'.',
                'topic' => 'Introduce our business category, '.$category.', and explain what customers can find out from us. Only mention specific services supported by our business description.',
            ];
        }
        if (filled(data_get($snapshot, 'regularHours.periods')) || filled(data_get($snapshot, 'storefrontAddress'))) {
            $ideas['visit'] = [
                'title' => 'Help customers plan a visit',
                'description' => 'Use the address and opening hours saved in your profile.',
                'topic' => 'Help customers plan a visit using the supplied address and regular hours. Do not claim special or holiday hours. Invite customers to check current hours before travelling.',
            ];
        }
        if (filled(data_get($snapshot, 'websiteUri'))) {
            $ideas['website'] = [
                'title' => 'Bring customers to your website',
                'description' => 'Invite people to learn more with a link to your website.',
                'topic' => 'Invite customers to explore our website to learn more about the business. Use LEARN_MORE with the supplied website URL; do not invent what the website contains.',
            ];
        }

        return $ideas;
    }

    /** @return array<string, mixed> */
    public function context(BusinessProfileConnection $connection): array
    {
        $snapshot = $connection->audits()->where('status', BusinessProfileAudit::STATUS_COMPLETED)->latest('id')->value('snapshot') ?? [];
        $context = array_intersect_key($snapshot, array_flip(['title', 'profile', 'categories', 'regularHours', 'storefrontAddress', 'websiteUri', 'phoneNumbers']));
        if (blank($context['websiteUri'] ?? null) && $domain = $connection->website->primaryDomain()?->domain) {
            $context['websiteUri'] = 'https://'.$domain;
        }

        return $context;
    }
}
