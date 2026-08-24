<?php

return [
    'automatic_follow_ups_enabled' => true,

    'timing' => [
        'cold_retry_days' => 4,
        'final_follow_up_days' => 6,
        'post_video_follow_up_days' => 3,
    ],

    'maximum_follow_up_attempts' => 2,

    'temperature_thresholds' => [
        'warm' => 3,
        'hot' => 10,
    ],

    'ignored_engagement_sources' => ['scanner'],

    'scanner_detection' => [
        'headers' => [
            'purpose' => ['prefetch'],
            'x-purpose' => ['preview', 'prefetch'],
            'x-moz' => ['prefetch'],
        ],
        'user_agent_patterns' => [
            'barracuda',
            'googleimageproxy',
            'mimecast',
            'proofpoint',
            'safelinks',
            'spambayes',
            'urlscan',
        ],
    ],

    'scoring' => [
        'email_opened' => ['first' => 1, 'repeat' => 1, 'max_awards' => 2, 'repeat_award_after_minutes' => 60],
        'audit_clicked' => ['first' => 5, 'repeat' => 5, 'max_awards' => 2, 'repeat_award_after_minutes' => 60],
        'sitewell_clicked' => ['first' => 5, 'repeat' => 5, 'max_awards' => 2, 'repeat_award_after_minutes' => 60],
        'personalised_video_clicked' => ['first' => 10, 'repeat' => 10, 'max_awards' => 2, 'repeat_award_after_minutes' => 60],
        'booking_page_clicked' => ['first' => 20, 'repeat' => 0, 'max_awards' => 1, 'repeat_award_after_minutes' => 60],
        'reply_received' => ['first' => 0, 'repeat' => 0, 'max_awards' => 1, 'repeat_award_after_minutes' => 0],
    ],

    'templates' => [
        'cold_follow_up' => [
            'subject' => null,
            'body' => null,
        ],
        'final_follow_up' => [
            'subject' => null,
            'body' => "Hi {contact_name},\n\nJust wanted to quickly follow up on the website audit I sent over.\n\nNo worries if the timing is not right.\n\nCheers,\nRoss",
        ],
        'personalised_video' => [
            'subject' => null,
            'body' => "Hi {contact_name},\n\nJust following on from my last email to you, I’ve recorded a quick video showing what we’d work on straight away to improve your website and its rankings.\n\nTake a look and let me know what you think!\n\nCheers,\nRoss",
        ],
        'post_video_follow_up' => [
            'subject' => null,
            'body' => "Hi {contact_name},\n\nJust wanted to follow up on the video I sent over.\n\nHopefully it gave you a better idea of what I’d work on first.\n\nIs improving the website / Google rankings something you’re looking at at the moment?\n\nCheers,\nRoss",
        ],
    ],
];
