<?php

return [
    'agency_url' => 'https://sitewell.digizu.co.uk/agencies',
    'partner_follow_up_working_days' => 6,
    'partner_templates' => [
        'web_design_agency' => [
            'initial' => ['subject' => 'Fancy offering SEO without doing the work?', 'body' => "Hi {first_name},\n\nI'm Ross, a fellow web developer from Doncaster.\n\nJust reaching out as I offer a white-label SEO service for web agencies, so you can offer SEO to your existing clients without having to do all the work yourself.\n\nYou keep your clients, set your own prices, and we do the SEO work. Hopefully everyone makes a few quid along the way 😂\n\nI've recorded a quick video explaining how it all works if you fancy a look:\n\n{agency_url}\n\nCheers,\nRoss"],
            'follow_up' => ['subject' => 'Re: Fancy offering SEO without doing the work?', 'body' => "Hi {first_name},\n\nJust following up on this in case it got buried!\n\nIf you've got clients asking about SEO, I'd be happy to have a quick chat about how we could work together.\n\nCheers,\nRoss"],
        ],
        'freelance_web_developer' => [
            'initial' => ['subject' => 'Fancy making a bit more from your web clients?', 'body' => "Hi {first_name},\n\nI'm Ross, a fellow web developer from Doncaster.\n\nJust reaching out as I offer a white-label SEO service that might be handy if you've got clients asking about Google rankings.\n\nBasically, you can offer SEO alongside your websites without having to do the work yourself.\n\nYou keep the client, charge whatever you like, and we handle the SEO. A nice bit of extra monthly income without adding more to your workload 😂\n\nI've recorded a quick video explaining how it works if you fancy a look:\n\n{agency_url}\n\nCheers,\nRoss"],
            'follow_up' => ['subject' => 'Re: Fancy making a bit more from your web clients?', 'body' => "Hi {first_name},\n\nJust following up on this!\n\nIf you've got any clients who could do with a bit of SEO help, give me a shout. Could be a nice way to bring in some extra recurring work.\n\nCheers,\nRoss"],
        ],
    ],

    'automatic_follow_ups_enabled' => true,

    'timing' => [
        'cold_retry_days' => 4,
        'final_follow_up_days' => 6,
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
        'initial_cold_outreach_with_video' => [
            'subject' => null,
            'body' => "Hi there,\n\nI was just having a look at your website and thought I’d say hello.\n\nI run a web & SEO company in Doncaster, helping businesses improve their websites and get found more on Google and AI.\n\nI’ve put together a quick website audit for you and recorded a short video to introduce myself and explain a little bit about what we do.\n\nLet me know if it’s something you’d be interested in having a chat about!\n\nCheers,\nRoss",
        ],
        'cold_follow_up' => [
            'subject' => null,
            'body' => null,
        ],
        'cold_follow_up_with_video' => [
            'subject' => null,
            'body' => "Hi there,\n\nJust checking you got my last email and had a chance to watch the quick video.\n\nLet me know what you think!\n\nCheers,\nRoss",
        ],
        'final_follow_up' => [
            'subject' => null,
            'body' => "Hi {contact_name},\n\nJust wanted to quickly follow up on the website audit I sent over.\n\nNo worries if the timing is not right.\n\nCheers,\nRoss",
        ],
        'personalised_video' => [
            'subject' => 'Quick introduction from me',
            'body' => "Hi there,\n\nJust following up from the website audit I sent over a little while ago.\n\nWanted to send you a really quick video to introduce myself and explain a little more about what we do.\n\nLet me know if it's something you'd be interested in!\n\nCheers,\nRoss",
        ],
    ],
];
