<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => bcrypt('password123'),
                'role' => User::ROLE_ADMIN,
            ]
        );

        $client = User::query()->firstOrCreate(
            ['email' => 'client@example.com'],
            [
                'name' => 'Client User',
                'password' => bcrypt('password123'),
                'role' => User::ROLE_USER,
            ]
        );

        if (Website::query()->exists()) {
            return;
        }

        $website = $admin->websites()->create([
            'name' => 'Northwind Studio',
            'is_active' => true,
            'auto_discovered' => false,
            'email_enabled' => true,
            'email_recipients' => ['hello@northwind.example'],
            'webhook_enabled' => true,
            'webhook_url' => 'https://example.com/webhook',
            'first_seen_at' => now()->subDays(3),
        ]);

        $website->domains()->create([
            'domain' => 'northwind.example',
            'is_primary' => true,
        ]);

        $form = $website->forms()->create([
            'name' => 'Contact form',
            'slug' => 'contact-form',
            'is_active' => true,
            'auto_discovered' => false,
            'email_enabled_override' => true,
            'email_recipients_override' => ['hello@northwind.example'],
            'first_seen_at' => now()->subDays(2),
        ]);

        $website->forms()->create([
            'name' => 'Quote request',
            'slug' => 'quote-request',
            'is_active' => true,
            'auto_discovered' => false,
            'first_seen_at' => now()->subDays(1),
        ]);

        $form->submissions()->createMany([
            [
                'website_id' => $website->id,
                'source_domain' => 'northwind.example',
                'source_url' => 'https://northwind.example/contact',
                'data' => [
                    'name' => 'Ada Lovelace',
                    'email' => 'ada@example.com',
                    'message' => 'Hello! I would love a quote for a new website.',
                ],
                'ip_address' => '203.0.113.1',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => now()->subHours(4),
            ],
            [
                'website_id' => $website->id,
                'source_domain' => 'northwind.example',
                'source_url' => 'https://northwind.example/contact',
                'data' => [
                    'name' => 'Grace Hopper',
                    'email' => 'grace@example.com',
                    'message' => 'Can you help with a CRM integration?',
                ],
                'ip_address' => '203.0.113.2',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => now()->subHours(1),
            ],
        ]);

        $clientWebsite = $client->websites()->create([
            'name' => 'Acme Marketing',
            'is_active' => true,
            'auto_discovered' => false,
            'email_enabled' => true,
            'email_recipients' => ['support@acme.example'],
            'webhook_enabled' => false,
            'first_seen_at' => now()->subDays(2),
        ]);

        $clientWebsite->domains()->create([
            'domain' => 'acme.example',
            'is_primary' => true,
        ]);

        $clientForm = $clientWebsite->forms()->create([
            'name' => 'Booking request',
            'slug' => 'booking-request',
            'is_active' => true,
            'auto_discovered' => false,
            'email_enabled_override' => true,
            'email_recipients_override' => ['support@acme.example'],
            'first_seen_at' => now()->subDay(),
        ]);

        $clientWebsite->forms()->create([
            'name' => 'Newsletter signup',
            'slug' => 'newsletter-signup',
            'is_active' => true,
            'auto_discovered' => false,
            'first_seen_at' => now()->subHours(6),
        ]);

        $clientForm->submissions()->createMany([
            [
                'website_id' => $clientWebsite->id,
                'source_domain' => 'acme.example',
                'source_url' => 'https://acme.example/book',
                'data' => [
                    'name' => 'Linus Torvalds',
                    'email' => 'linus@example.com',
                    'message' => 'I would like to book a discovery call.',
                ],
                'ip_address' => '198.51.100.7',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => now()->subHours(2),
            ],
            [
                'website_id' => $clientWebsite->id,
                'source_domain' => 'acme.example',
                'source_url' => 'https://acme.example/book',
                'data' => [
                    'name' => 'Margaret Hamilton',
                    'email' => 'margaret@example.com',
                    'message' => 'Please send over the next steps.',
                ],
                'ip_address' => '198.51.100.8',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => now()->subMinutes(30),
            ],
        ]);
    }
}
