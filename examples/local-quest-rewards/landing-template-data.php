<?php
declare(strict_types=1);

function lqr_landing_templates(): array
{
    return [
        'coffee' => [
            'id' => 'coffee',
            'quest_id' => 'downtown-coffee-checkin',
            'label' => 'Downtown Coffee Quest',
            'short_label' => 'Coffee Quest',
            'theme' => 'coffee',
            'accent' => '#a34827',
            'accent_2' => '#c99035',
            'dark' => '#2b160c',
            'soft' => '#fff7ef',
            'hero_image' => 'assets/landing-templates/coffee-hero.svg',
            'reward_image' => 'assets/landing-templates/coffee-reward.svg',
            'cta_image' => 'assets/landing-templates/coffee-cta-bg.svg',
            'eyebrow' => 'Explore. Check in. Earn rewards.',
            'headline' => 'Downtown Coffee Quest',
            'subhead' => 'Check in at local coffee shops, complete the quest, and earn a $5 Coffee Microgift.',
            'primary_cta' => 'Start the Quest',
            'secondary_cta' => 'How It Works',
            'social_label' => 'Loved by 2,500+ coffee explorers',
            'reward_title' => '$5 Coffee Microgift',
            'reward_body' => 'Redeem at participating coffee shops across Downtown. Because great coffee is better when it is local.',
            'reward_card_title' => 'Coffee Microgift',
            'reward_value' => '$5',
            'reward_validity' => 'Valid at participating shops',
            'reward_location_label' => 'See all locations in the app',
            'trust_label' => 'A local experience you can trust',
            'cta_headline' => 'Ready to explore Downtown and earn rewards?',
            'cta_body' => 'Great coffee. Local shops. Real rewards.',
            'steps' => [
                ['icon' => 'qr', 'title' => 'Scan to Check In', 'body' => 'Find a participating coffee shop and scan the QR code to check in.'],
                ['icon' => 'checklist', 'title' => 'Complete the Quest', 'body' => 'Check in at 3 unique coffee shops in Downtown to complete your quest.'],
                ['icon' => 'gift', 'title' => 'Earn Your Reward', 'body' => 'Unlock a $5 Coffee Microgift to use at local coffee shops.'],
                ['icon' => 'wallet', 'title' => 'Track in Wallet', 'body' => 'View your progress and redeem rewards all in your wallet.'],
            ],
            'how' => [
                ['title' => 'Find & Check In', 'body' => 'Visit a participating coffee shop and scan the QR code to check in.'],
                ['title' => 'Explore & Complete', 'body' => 'Check in at 3 unique coffee shops in Downtown to complete your quest.'],
                ['title' => 'Get Rewarded', 'body' => 'Unlock your $5 Coffee Microgift and enjoy your next cup on us.'],
            ],
            'stats' => [
                ['value' => '50+', 'label' => 'Local Coffee Shops Participating'],
                ['value' => '2,500+', 'label' => 'Happy Questers'],
                ['value' => '15,000+', 'label' => 'Rewards Claimed'],
                ['value' => '4.8', 'label' => 'App Store Rating'],
                ['value' => 'Secure', 'label' => 'Your data is safe with us'],
            ],
        ],
        'food-crawl' => [
            'id' => 'food-crawl',
            'quest_id' => 'food-crawl-three-stops',
            'label' => 'Food Crawl: Three Stops',
            'short_label' => 'Food Crawl',
            'theme' => 'food',
            'accent' => '#e44b34',
            'accent_2' => '#d8972f',
            'dark' => '#33150d',
            'soft' => '#fff4eb',
            'hero_image' => 'assets/landing-templates/food-crawl-hero.svg',
            'reward_image' => 'assets/landing-templates/food-crawl-reward.svg',
            'cta_image' => 'assets/landing-templates/food-crawl-cta-bg.svg',
            'eyebrow' => 'Explore. Check in. Earn rewards.',
            'headline' => 'Food Crawl: Three Stops',
            'subhead' => 'Visit three local restaurants, complete the crawl, and unlock a $10 Dining Microgift.',
            'primary_cta' => 'Start the Crawl',
            'secondary_cta' => 'How It Works',
            'social_label' => 'Loved by 2,500+ food explorers',
            'reward_title' => '$10 Dining Microgift',
            'reward_body' => 'Redeem at participating restaurants across town. Because great food is better when it is local.',
            'reward_card_title' => 'Dining Microgift',
            'reward_value' => '$10',
            'reward_validity' => 'Valid at participating restaurants',
            'reward_location_label' => 'See all locations in the app',
            'trust_label' => 'A local experience you can trust',
            'cta_headline' => 'Ready to explore. Ready to eat. Start your Food Crawl today!',
            'cta_body' => 'Great bites. Local spots. Real rewards.',
            'steps' => [
                ['icon' => 'food', 'title' => 'Scan to Check In', 'body' => 'Check in at participating restaurants by scanning the QR code at each stop.'],
                ['icon' => 'pin', 'title' => 'Visit Three Stops', 'body' => 'Explore and enjoy three local restaurants on your food crawl adventure.'],
                ['icon' => 'gift', 'title' => 'Earn Your Reward', 'body' => 'Complete all three stops to unlock your $10 Dining Microgift.'],
                ['icon' => 'wallet', 'title' => 'Track in Wallet', 'body' => 'View your progress and redeem your reward all in your wallet.'],
            ],
            'how' => [
                ['title' => 'Find & Check In', 'body' => 'Visit 3 participating restaurants and scan the QR code to check in.'],
                ['title' => 'Complete the Crawl', 'body' => 'Check in at all three stops to complete your food crawl.'],
                ['title' => 'Get Rewarded', 'body' => 'Unlock your $10 Dining Microgift and enjoy your next meal on us.'],
            ],
            'stats' => [
                ['value' => '75+', 'label' => 'Local Restaurants Participating'],
                ['value' => '3,200+', 'label' => 'Happy Foodies'],
                ['value' => '18,000+', 'label' => 'Rewards Claimed'],
                ['value' => '4.9', 'label' => 'App Store Rating'],
                ['value' => 'Secure', 'label' => 'Your data is safe with us'],
            ],
        ],
    ];
}

function lqr_landing_template_key(array $templates, string $requested = ''): string
{
    $requested = trim($requested);
    if ($requested !== '' && isset($templates[$requested])) return $requested;
    return array_key_first($templates) ?: 'coffee';
}

function lqr_selected_landing_template(array $state, array $templates): string
{
    $selected = (string)($state['partner_app']['selected_landing_template'] ?? '');
    return lqr_landing_template_key($templates, $selected);
}
