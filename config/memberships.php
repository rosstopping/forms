<?php

return [
    'website_ai_questions_per_week' => 25,

    'growth_offer' => [
        'starts_at' => '2026-09-07 00:00:00',
        'ends_at' => '2026-12-31 23:59:59',
        'price' => 316,
        'discount_percentage' => 20,
        'stripe_price_id' => env('STRIPE_GROWTH_2026_OFFER_PRICE_ID'),
    ],

    'plans' => [
        'essential' => [
            'name' => 'Essential',
            'price' => 149,
            'description' => 'For a business that wants a specialist team looking after its website and enquiries.',
            'summary' => 'One website included',
            'features' => [
                'Free website included if you need one',
                'Bring your existing site or take your Sitewell website with you',
                'Website health and SEO audits managed by our specialists',
                'Practical fixes and a clear weekly report',
                'Managed forms, spam protection, and customer replies',
                'Lead inbox, CRM, notes, and follow-up reminders',
            ],
            'stripe_price_id' => env('STRIPE_ESSENTIAL_PRICE_ID'),
        ],
        'growth' => [
            'name' => 'Growth',
            'price' => 395,
            'description' => 'For businesses ready to have their search visibility actively managed and improved.',
            'summary' => 'Everything in Essential',
            'features' => [
                'Free website included if you need one',
                'Bring your existing site or take your Sitewell website with you',
                'Search performance interpreted by our SEO specialists',
                'Striking-distance keywords and commercial opportunities',
                'SEO improvements for existing pages',
                'New landing pages and content prepared for you',
                'Up to one scheduled content improvement per week, prepared for review',
                'Priority support',
            ],
            'stripe_price_id' => env('STRIPE_GROWTH_PRICE_ID'),
        ],
        'complete' => [
            'name' => 'Complete',
            'price' => 695,
            'description' => 'For businesses that want one specialist team managing their website, search, and local presence.',
            'summary' => 'Everything in Growth',
            'features' => [
                'Free website included if you need one',
                'Bring your existing site or take your Sitewell website with you',
                'Up to three scheduled content improvements per week, prepared for review',
                'Specialist Google Business Profile management',
                'Profile health checks and recommended changes',
                'Google posts prepared for your business',
                'Customer review replies prepared for you',
                'Advanced lead handling and dedicated support',
                'Dedicated website and SEO specialist',
            ],
            'stripe_price_id' => env('STRIPE_COMPLETE_PRICE_ID'),
        ],
    ],
];
