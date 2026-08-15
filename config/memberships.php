<?php

return [
    'website_ai_questions_per_week' => 25,

    'plans' => [
        'essential' => [
            'name' => 'Essential',
            'price' => 149,
            'description' => 'For a business that needs its website and leads properly looked after.',
            'summary' => 'One website included',
            'features' => [
                'Free website included if you need one',
                'Website health checks and practical fixes',
                'Weekly email health reports',
                'Forms, spam protection, and automatic replies',
                'Lead inbox, CRM, notes, and follow-up reminders',
            ],
            'stripe_price_id' => env('STRIPE_ESSENTIAL_PRICE_ID'),
        ],
        'growth' => [
            'name' => 'Growth',
            'price' => 249,
            'description' => 'For businesses ready to turn search visibility into a steady growth channel.',
            'summary' => 'Everything in Essential',
            'features' => [
                'Google Search Console performance',
                'Search opportunities and recommendations',
                'Automated, reviewable content generation',
                'Content planning and improvement workflow',
                'Priority support',
            ],
            'stripe_price_id' => env('STRIPE_GROWTH_PRICE_ID'),
        ],
        'complete' => [
            'name' => 'Complete',
            'price' => 399,
            'description' => 'For businesses that want their website and local presence working together.',
            'summary' => 'Everything in Growth',
            'features' => [
                'Google Business Profile management',
                'Profile health checks and recommended changes',
                'Generated Google post drafts',
                'Review replies in approval mode',
                'Advanced automations and dedicated support',
                'Website data AI assistant (25 questions per week)',
            ],
            'stripe_price_id' => env('STRIPE_COMPLETE_PRICE_ID'),
        ],
    ],
];
