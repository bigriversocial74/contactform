<?php
declare(strict_types=1);

require_once __DIR__ . '/loyalty-quest-campaign-type.php';
require_once __DIR__ . '/public-donations-campaign-type.php';

/**
 * Microgifter Campaign Type Registry v2.1
 *
 * Central source of truth for merchant campaign type behavior, labels,
 * public routes, CRM sources, defaults, and internal-only flags.
 */

function mg_campaign_type_registry(): array
{
    $registry = [
        'newsletter_signup' => [
            'key' => 'newsletter_signup',
            'label' => 'Signup Reward',
            'legacy_label' => 'Newsletter Signup',
            'category' => 'customer_acquisition',
            'description' => 'Capture customer contact information and issue a welcome reward.',
            'merchant_use_case' => 'List growth, website forms, first-time customer incentives, and general lead capture.',
            'public_path' => '/newsletter-signup.php',
            'submit_endpoint' => '/api/public/campaigns/signup.php',
            'source_type' => 'newsletter_signup',
            'event_type' => 'form.submitted',
            'requires_reward_template' => true,
            'public_enabled' => true,
            'crm_enabled' => true,
            'embed_allowed' => true,
            'internal_only' => false,
            'wallet_issue_mode' => 'instant_reward',
            'default_status' => 'draft',
            'analytics_bucket' => 'signup_reward',
            'default_copy' => [
                'title' => 'Join the list and get a reward',
                'form_headline' => 'Join our rewards list',
                'description' => 'Sign up for merchant updates and receive a wallet-ready reward.',
                'form_description' => 'Enter your info to join the list and unlock your reward.',
                'success_message' => 'Signup reward issued.',
                'quantity_limit' => '',
                'per_user_limit' => '1',
            ],
            'rules_schema' => ['mode' => 'instant_reward', 'entry_reward_enabled' => true],
        ],
        'contest_giveaway' => [
            'key' => 'contest_giveaway',
            'label' => 'Contest / Giveaway',
            'category' => 'growth_loop',
            'description' => 'Collect contest entries and issue prizes by first-X, instant, manual, or random draw rules.',
            'merchant_use_case' => 'Giveaways, prize drawings, social contests, launch promos, and event entries.',
            'public_path' => '/contest.php',
            'submit_endpoint' => '/api/public/campaigns/contest-entry.php',
            'source_type' => 'contest_entry',
            'event_type' => 'contest.entered',
            'requires_reward_template' => true,
            'public_enabled' => true,
            'crm_enabled' => true,
            'embed_allowed' => true,
            'internal_only' => false,
            'wallet_issue_mode' => 'rules_based',
            'default_status' => 'draft',
            'analytics_bucket' => 'contest',
            'default_copy' => [
                'title' => 'Enter to win a local reward',
                'form_headline' => 'Enter the giveaway',
                'description' => 'Enter the campaign for a chance to win a merchant reward.',
                'form_description' => 'Enter your info below to join the giveaway.',
                'success_message' => 'Contest entry recorded.',
                'quantity_limit' => '100',
                'per_user_limit' => '1',
                'contest_mode' => 'first_x',
                'contest_winner_limit' => '100',
                'contest_rules' => 'No purchase necessary. One entry per person.',
            ],
            'rules_schema' => [
                'mode' => ['first_x', 'instant_reward', 'random_draw', 'manual_winner'],
                'winner_limit' => true,
                'draw_at' => true,
                'entry_reward_enabled' => true,
                'official_rules' => true,
            ],
        ],
        'qr_reward_drop' => [
            'key' => 'qr_reward_drop',
            'label' => 'QR Reward Drop',
            'category' => 'customer_acquisition',
            'description' => 'Turn physical QR placements into wallet-ready reward claims.',
            'merchant_use_case' => 'Table tents, flyers, events, in-store posters, hospitality promotions, and local discovery.',
            'public_path' => '/qr-reward.php',
            'submit_endpoint' => '/api/public/campaigns/qr-pickup.php',
            'source_type' => 'qr_scan',
            'event_type' => 'qr.scanned',
            'requires_reward_template' => true,
            'public_enabled' => true,
            'crm_enabled' => true,
            'embed_allowed' => true,
            'internal_only' => false,
            'wallet_issue_mode' => 'instant_reward',
            'default_status' => 'draft',
            'analytics_bucket' => 'qr_drop',
            'default_copy' => [
                'title' => 'Scan and claim this reward',
                'form_headline' => 'Claim your QR reward',
                'description' => 'Scan or open this QR reward drop to add the reward to your wallet.',
                'form_description' => 'Enter your info to claim this QR reward.',
                'success_message' => 'QR reward added to wallet.',
                'quantity_limit' => '100',
                'per_user_limit' => '1',
            ],
            'rules_schema' => ['mode' => 'qr_claim', 'entry_reward_enabled' => true],
        ],
        'referral_reward' => [
            'key' => 'referral_reward',
            'label' => 'Referral Reward',
            'category' => 'growth_loop',
            'description' => 'Capture referral intent and issue a reward through the campaign wallet flow.',
            'merchant_use_case' => 'Bring-a-friend offers, invite campaigns, community referrals, and word-of-mouth loops.',
            'public_path' => '/referral-reward.php',
            'submit_endpoint' => '/api/public/campaigns/engage.php',
            'source_type' => 'referral',
            'event_type' => 'campaign.engaged',
            'requires_reward_template' => true,
            'public_enabled' => true,
            'crm_enabled' => true,
            'embed_allowed' => true,
            'internal_only' => false,
            'wallet_issue_mode' => 'instant_reward',
            'default_status' => 'draft',
            'analytics_bucket' => 'referral',
            'default_copy' => [
                'title' => 'Refer a friend and get rewarded',
                'form_headline' => 'Join the referral reward',
                'description' => 'Capture referral interest and connect the customer to a merchant reward.',
                'form_description' => 'Tell us who referred you or who you want to invite.',
                'success_message' => 'Referral response recorded.',
                'quantity_limit' => '',
                'per_user_limit' => '1',
                'referral_instructions' => 'Tell us who referred you or who you want to invite.',
            ],
            'rules_schema' => ['mode' => 'referral_capture', 'instructions' => true],
        ],
        'birthday_vip' => [
            'key' => 'birthday_vip',
            'label' => 'Birthday / VIP Club',
            'legacy_label' => 'Birthday / VIP',
            'category' => 'loyalty_retention',
            'description' => 'Build a birthday or VIP list for retention rewards and loyalty follow-up.',
            'merchant_use_case' => 'Birthday clubs, VIP enrollment, loyalty lists, and recurring customer retention.',
            'public_path' => '/birthday-vip.php',
            'submit_endpoint' => '/api/public/campaigns/engage.php',
            'source_type' => 'birthday_vip',
            'event_type' => 'campaign.engaged',
            'requires_reward_template' => true,
            'public_enabled' => true,
            'crm_enabled' => true,
            'embed_allowed' => true,
            'internal_only' => false,
            'wallet_issue_mode' => 'instant_reward',
            'default_status' => 'draft',
            'analytics_bucket' => 'birthday_vip',
            'default_copy' => [
                'title' => 'Join the birthday VIP list',
                'form_headline' => 'Join our birthday club',
                'description' => 'Collect birthday month and VIP interest for future merchant rewards.',
                'form_description' => 'Enter your info and birthday month to join the VIP list.',
                'success_message' => 'Birthday VIP signup recorded.',
                'quantity_limit' => '',
                'per_user_limit' => '1',
                'vip_instructions' => 'Join our birthday club and receive a reward during your birthday month.',
            ],
            'rules_schema' => ['mode' => 'birthday_capture', 'instructions' => true],
        ],
        'agent_offer' => [
            'key' => 'agent_offer',
            'label' => 'Agent Offer / Intent Capture',
            'legacy_label' => 'Agent Offer',
            'category' => 'agentic_commerce',
            'description' => 'Capture customer intent for agent-discoverable merchant offers.',
            'merchant_use_case' => 'AI-assisted local offer discovery, intent capture, and future agent-managed commerce.',
            'public_path' => '/agent-offer.php',
            'submit_endpoint' => '/api/public/campaigns/engage.php',
            'source_type' => 'agent_discovery',
            'event_type' => 'campaign.engaged',
            'requires_reward_template' => true,
            'public_enabled' => true,
            'crm_enabled' => true,
            'embed_allowed' => true,
            'internal_only' => false,
            'wallet_issue_mode' => 'instant_reward',
            'default_status' => 'draft',
            'analytics_bucket' => 'agent_offer',
            'default_copy' => [
                'title' => 'Tell us what local reward you want',
                'form_headline' => 'Request a local offer',
                'description' => 'Capture customer interest for agent-discoverable merchant offers.',
                'form_description' => 'Tell the merchant what kind of reward or offer interests you.',
                'success_message' => 'Offer interest recorded.',
                'quantity_limit' => '',
                'per_user_limit' => '1',
                'agent_offer_instructions' => 'Tell us what you are looking for and we will recommend a local reward.',
            ],
            'rules_schema' => ['mode' => 'agent_interest', 'instructions' => true],
        ],
        'survey_feedback_reward' => [
            'key' => 'survey_feedback_reward',
            'label' => 'Survey / Feedback Reward',
            'category' => 'loyalty_retention',
            'description' => 'Ask customers for structured feedback or a short survey response, then issue a wallet-ready reward.',
            'merchant_use_case' => 'Post-visit feedback, customer satisfaction, product feedback, event feedback, and service-recovery listening loops.',
            'public_path' => '/survey-feedback.php',
            'submit_endpoint' => '/api/public/campaigns/survey-feedback.php',
            'source_type' => 'survey_feedback',
            'event_type' => 'survey_feedback.submitted',
            'requires_reward_template' => true,
            'public_enabled' => true,
            'crm_enabled' => true,
            'embed_allowed' => true,
            'internal_only' => false,
            'wallet_issue_mode' => 'survey_reward',
            'default_status' => 'draft',
            'analytics_bucket' => 'survey_feedback',
            'default_copy' => [
                'title' => 'Share feedback and get a reward',
                'form_headline' => 'Tell us how we did',
                'description' => 'Answer a quick feedback question and receive a Microgifter reward.',
                'form_description' => 'Rate your experience, share a short note, and unlock your reward.',
                'success_message' => 'Feedback received. Your reward has been sent.',
                'quantity_limit' => '',
                'per_user_limit' => '1',
                'survey_prompt' => 'How was your experience?',
                'survey_rating_required' => '1',
                'survey_feedback_required' => '1',
            ],
            'rules_schema' => [
                'mode' => 'survey_feedback',
                'rating_required' => true,
                'feedback_required' => true,
                'prompt' => true,
            ],
        ],
        'check_in_reward' => [
            'key' => 'check_in_reward',
            'label' => 'Check-In Reward',
            'category' => 'loyalty_retention',
            'description' => 'Ask customers to share browser location, match them to a registered merchant location, and issue a wallet-ready check-in reward.',
            'merchant_use_case' => 'QR/location/event check-ins, venue attendance, in-store visits, hospitality promos, and location-based loyalty.',
            'public_path' => '/check-in-reward.php',
            'submit_endpoint' => '/api/public/campaigns/check-in.php',
            'source_type' => 'check_in_reward',
            'event_type' => 'check_in.completed',
            'requires_reward_template' => true,
            'public_enabled' => true,
            'crm_enabled' => true,
            'embed_allowed' => true,
            'internal_only' => false,
            'wallet_issue_mode' => 'geo_check_in_reward',
            'default_status' => 'draft',
            'analytics_bucket' => 'check_in_reward',
            'default_copy' => [
                'title' => 'Check in and get a reward',
                'form_headline' => 'Check in at this location',
                'description' => 'Use your browser location to verify you are near a registered merchant location and unlock a Microgifter reward.',
                'form_description' => 'Allow location access, enter your info, and Microgifter will match you to the nearest registered merchant location.',
                'success_message' => 'Check-in verified. Your reward has been sent.',
                'quantity_limit' => '',
                'per_user_limit' => '1',
                'check_in_radius_meters' => '150',
                'check_in_location_required' => '1',
            ],
            'rules_schema' => [
                'mode' => 'geo_check_in',
                'browser_location_required' => true,
                'merchant_location_match' => true,
                'location_required' => true,
                'radius_meters' => true,
            ],
        ],
        'instant_win_reward' => [
            'key' => 'instant_win_reward',
            'label' => 'Spin / Scratch Instant Win',
            'category' => 'engagement_rewards',
            'description' => 'Let customers play a quick scratch-card or instant reveal game for a chance to unlock a wallet-ready reward.',
            'merchant_use_case' => 'Gamified promos, restaurant/bar giveaways, event booths, local prize drops, launch campaigns, and high-engagement reward reveals.',
            'public_path' => '/instant-win.php',
            'submit_endpoint' => '/api/public/campaigns/instant-win.php',
            'source_type' => 'instant_win_reward',
            'event_type' => 'instant_win.played',
            'requires_reward_template' => true,
            'public_enabled' => true,
            'crm_enabled' => true,
            'embed_allowed' => true,
            'internal_only' => false,
            'wallet_issue_mode' => 'instant_win_reward',
            'default_status' => 'draft',
            'analytics_bucket' => 'instant_win',
            'default_copy' => [
                'title' => 'Scratch to win a local reward',
                'form_headline' => 'Scratch and reveal your instant win',
                'description' => 'Enter your info, reveal the scratch card, and see if you unlocked a Microgifter reward.',
                'form_description' => 'Every play is tracked in the merchant CRM. Winners receive a reward in their Microgifter Inbox.',
                'success_message' => 'Instant win result recorded.',
                'quantity_limit' => '100',
                'per_user_limit' => '1',
                'instant_win_odds_percent' => '100',
                'instant_win_no_win_message' => 'Not a winner this time — thanks for playing.',
            ],
            'rules_schema' => [
                'mode' => ['scratch_card', 'spin_wheel'],
                'odds_percent' => true,
                'no_win_message' => true,
                'entry_reward_enabled' => true,
            ],
        ],
        'stamp_card_reward' => [
            'key' => 'stamp_card_reward',
            'label' => 'Stamp Card / Visit Tracker',
            'category' => 'loyalty_retention',
            'description' => 'Track repeat visits or purchases as stamps, then unlock a wallet-ready reward when the customer reaches the required stamp count.',
            'merchant_use_case' => 'Visit tracking, buy-five-get-one, cafe/restaurant loyalty, service repeat visits, event punches, and local retention programs.',
            'public_path' => '/stamp-card.php',
            'submit_endpoint' => '/api/public/campaigns/stamp-card.php',
            'source_type' => 'stamp_card_reward',
            'event_type' => 'stamp_card.stamped',
            'requires_reward_template' => true,
            'public_enabled' => true,
            'crm_enabled' => true,
            'embed_allowed' => true,
            'internal_only' => false,
            'wallet_issue_mode' => 'stamp_card_unlock_reward',
            'default_status' => 'draft',
            'analytics_bucket' => 'stamp_card',
            'default_copy' => [
                'title' => 'Collect stamps and unlock a reward',
                'form_headline' => 'Add a stamp to your visit card',
                'description' => 'Check in after each visit or purchase. When your stamp card is full, Microgifter sends the reward to your Inbox.',
                'form_description' => 'Enter your info to add today’s stamp. Your progress is tracked in the merchant CRM.',
                'success_message' => 'Stamp recorded. Reward unlock checked.',
                'quantity_limit' => '',
                'per_user_limit' => '1',
                'stamp_required_count' => '5',
                'stamp_label' => 'Visit',
                'stamp_cooldown_hours' => '0',
            ],
            'rules_schema' => [
                'mode' => 'verified_stamp_card',
                'required_count' => true,
                'stamp_label' => true,
                'cooldown_hours' => true,
                'cashier_verification_required' => true,
            ],
        ],
        'rsvp_event_reward' => [
            'key' => 'rsvp_event_reward',
            'label' => 'RSVP / Event Attendance Reward',
            'category' => 'loyalty_retention',
            'description' => 'Capture event RSVPs, track attendance confirmation, and issue a wallet-ready reward only after attendance is confirmed.',
            'merchant_use_case' => 'RSVP campaigns, launch parties, tastings, classes, music/events, hospitality reservations, and post-event attendance rewards.',
            'public_path' => '/rsvp-event.php',
            'submit_endpoint' => '/api/public/campaigns/rsvp-event.php',
            'source_type' => 'rsvp_event_reward',
            'event_type' => 'rsvp_event.rsvped',
            'requires_reward_template' => true,
            'public_enabled' => true,
            'crm_enabled' => true,
            'embed_allowed' => true,
            'internal_only' => false,
            'wallet_issue_mode' => 'attendance_confirmed_reward',
            'default_status' => 'draft',
            'analytics_bucket' => 'rsvp_event',
            'default_copy' => [
                'title' => 'RSVP and earn an attendance reward',
                'form_headline' => 'Reserve your spot',
                'description' => 'RSVP for this merchant event. When attendance is confirmed, Microgifter sends the reward to your Inbox.',
                'form_description' => 'Enter your info to RSVP. Rewards are issued after event attendance is confirmed.',
                'success_message' => 'RSVP recorded. Attendance reward eligibility will be checked at the event.',
                'quantity_limit' => '',
                'per_user_limit' => '1',
                'rsvp_event_name' => 'Merchant event',
                'rsvp_event_date' => '',
                'rsvp_attendance_code' => '',
            ],
            'rules_schema' => [
                'mode' => 'rsvp_attendance',
                'event_name' => true,
                'event_date' => true,
                'attendance_code' => true,
            ],
        ],
        'watch_video_reward' => [
            'key' => 'watch_video_reward',
            'label' => 'Watch Video Reward',
            'category' => 'engagement_rewards',
            'description' => 'Reward customers for watching a YouTube or uploaded merchant video, with optional gift milestones by watch percentage.',
            'merchant_use_case' => 'Uploaded promos, product demos, artist videos, sponsor videos, training clips, hospitality promos, and watch-to-earn campaigns.',
            'public_path' => '/watch-reward.php',
            'submit_endpoint' => '/api/public/campaigns/watch-progress-v2.php',
            'source_type' => 'watch_video_reward',
            'event_type' => 'watch_reward.started',
            'requires_reward_template' => true,
            'public_enabled' => true,
            'crm_enabled' => true,
            'embed_allowed' => true,
            'internal_only' => false,
            'wallet_issue_mode' => 'video_milestone_reward',
            'default_status' => 'draft',
            'analytics_bucket' => 'watch_video_reward',
            'default_copy' => [
                'title' => 'Watch the video and unlock rewards',
                'form_headline' => 'Watch to unlock rewards',
                'description' => 'Watch this video and Microgifter will send rewards to your wallet as you reach the milestones.',
                'form_description' => 'Enter your info, watch the video, and unlock rewards based on watch progress.',
                'success_message' => 'Video reward progress recorded.',
                'quantity_limit' => '',
                'per_user_limit' => '3',
                'watch_video_provider' => 'youtube',
                'watch_video_url' => '',
                'watch_video_uploaded_url' => '',
                'watch_video_upload_asset_id' => '',
                'watch_video_required_percent' => '80',
                'watch_video_milestone_1_percent' => '25',
                'watch_video_milestone_2_percent' => '50',
                'watch_video_milestone_3_percent' => '80',
            ],
            'rules_schema' => [
                'mode' => 'video_watch_milestones',
                'video_provider' => ['youtube', 'uploaded'],
                'youtube_video_id' => true,
                'uploaded_asset_id' => true,
                'uploaded_video_url' => true,
                'required_percent' => true,
                'milestones' => true,
            ],
        ],
        'listen_music_reward' => [
            'key' => 'listen_music_reward',
            'label' => 'Listen Music Reward',
            'category' => 'engagement_rewards',
            'description' => 'Reward customers for listening to a Spotify song link or uploaded MP3/audio track.',
            'merchant_use_case' => 'Artist promos, music launches, podcast/audio previews, sponsor audio, and listen-to-earn campaigns.',
            'public_path' => '/listen-reward.php',
            'submit_endpoint' => '/api/public/campaigns/listen-progress.php',
            'source_type' => 'listen_music_reward',
            'event_type' => 'listen_reward.started',
            'requires_reward_template' => true,
            'public_enabled' => true,
            'crm_enabled' => true,
            'embed_allowed' => true,
            'internal_only' => false,
            'wallet_issue_mode' => 'audio_milestone_reward',
            'default_status' => 'draft',
            'analytics_bucket' => 'listen_music_reward',
            'default_copy' => [
                'title' => 'Listen and unlock rewards',
                'form_headline' => 'Listen to unlock rewards',
                'description' => 'Listen to this track and Microgifter will send rewards to your wallet as you reach the milestones.',
                'form_description' => 'Enter your info, listen to the track, and unlock rewards based on listen progress.',
                'success_message' => 'Music reward progress recorded.',
                'quantity_limit' => '',
                'per_user_limit' => '3',
                'listen_music_provider' => 'spotify',
                'listen_spotify_url' => '',
                'listen_audio_uploaded_url' => '',
                'listen_audio_upload_asset_id' => '',
                'listen_required_percent' => '80',
                'listen_milestone_1_percent' => '25',
                'listen_milestone_2_percent' => '50',
                'listen_milestone_3_percent' => '80',
                'listen_track_title' => '',
                'listen_artist_name' => '',
            ],
            'rules_schema' => [
                'mode' => 'audio_listen_milestones',
                'audio_provider' => ['spotify', 'uploaded'],
                'spotify_track_id' => true,
                'uploaded_asset_id' => true,
                'uploaded_audio_url' => true,
                'required_percent' => true,
                'milestones' => true,
            ],
        ],
        'customer_refund' => [
            'key' => 'customer_refund',
            'label' => 'Customer Refund / Make-Good',
            'category' => 'loyalty_retention',
            'description' => 'Merchant-only service recovery campaign for make-good vouchers and refund alternatives.',
            'merchant_use_case' => 'Apology vouchers, service recovery, make-good sends, and customer-save workflows.',
            'public_path' => '',
            'submit_endpoint' => '',
            'source_type' => 'customer_refund',
            'event_type' => 'customer_refund.issued',
            'requires_reward_template' => true,
            'public_enabled' => false,
            'crm_enabled' => true,
            'embed_allowed' => false,
            'internal_only' => true,
            'wallet_issue_mode' => 'merchant_initiated',
            'default_status' => 'draft',
            'analytics_bucket' => 'customer_refund',
            'default_copy' => [
                'title' => 'Customer make-good voucher',
                'form_headline' => 'Customer make-good voucher',
                'description' => 'Merchant-only campaign for issuing a service recovery voucher to a known customer.',
                'form_description' => 'Internal campaign. Customer submission page is disabled.',
                'success_message' => 'Customer make-good voucher issued.',
                'quantity_limit' => '',
                'per_user_limit' => '1',
            ],
            'rules_schema' => ['mode' => 'merchant_initiated', 'internal_only' => true],
        ],
    ];
    $registry['loyalty_quest'] = mg_loyalty_quest_campaign_definition();
    $registry['public_donation'] = mg_public_donations_campaign_definition();
    return $registry;
}

function mg_campaign_type_get(string $type): ?array
{
    $registry = mg_campaign_type_registry();
    return $registry[$type] ?? null;
}

function mg_campaign_type_keys(bool $includeInternal = true): array
{
    return array_keys(array_filter(
        mg_campaign_type_registry(),
        static fn(array $definition): bool => $includeInternal || empty($definition['internal_only'])
    ));
}

function mg_campaign_type_is_valid(string $type, bool $includeInternal = true): bool
{
    return in_array($type, mg_campaign_type_keys($includeInternal), true);
}

function mg_campaign_type_label(string $type): string
{
    return (string)(mg_campaign_type_get($type)['label'] ?? 'Campaign');
}

function mg_campaign_type_public_path(string $type): string
{
    return (string)(mg_campaign_type_get($type)['public_path'] ?? '/campaign.php');
}

function mg_campaign_type_public_enabled(string $type): bool
{
    return !empty(mg_campaign_type_get($type)['public_enabled']);
}

function mg_campaign_type_public_transactional(string $type): bool
{
    $definition = mg_campaign_type_get($type);
    if (!is_array($definition) || empty($definition['public_enabled'])) return false;
    return !array_key_exists('public_transactional', $definition) || !empty($definition['public_transactional']);
}

function mg_campaign_type_public_mode(string $type): string
{
    $definition = mg_campaign_type_get($type);
    if (!is_array($definition) || empty($definition['public_enabled'])) return 'internal';
    return (string)($definition['public_mode'] ?? (mg_campaign_type_public_transactional($type) ? 'transactional' : 'informational'));
}

function mg_campaign_type_submit_endpoint(string $type): string
{
    if (!mg_campaign_type_public_transactional($type)) return '';
    $endpoint = trim((string)(mg_campaign_type_get($type)['submit_endpoint'] ?? ''));
    return $endpoint !== '' ? $endpoint : '/api/public/campaigns/engage.php';
}

function mg_campaign_type_source(string $type): string
{
    return (string)(mg_campaign_type_get($type)['source_type'] ?? 'newsletter_signup');
}

function mg_campaign_type_event_type(string $type): string
{
    return (string)(mg_campaign_type_get($type)['event_type'] ?? 'campaign.engaged');
}

function mg_campaign_type_requires_reward_template(string $type, string $status = 'draft'): bool
{
    if ($status !== 'active') return false;
    return !empty(mg_campaign_type_get($type)['requires_reward_template']);
}

function mg_campaign_type_options(bool $includeInternal = false): array
{
    return array_values(array_map(
        static fn(array $definition): array => [
            'key' => $definition['key'],
            'label' => $definition['label'],
            'category' => $definition['category'],
            'description' => $definition['description'],
            'internal_only' => !empty($definition['internal_only']),
            'public_enabled' => !empty($definition['public_enabled']),
            'public_transactional' => mg_campaign_type_public_transactional((string)$definition['key']),
            'public_mode' => mg_campaign_type_public_mode((string)$definition['key']),
        ],
        array_filter(
            mg_campaign_type_registry(),
            static fn(array $definition): bool => $includeInternal || empty($definition['internal_only'])
        )
    ));
}

function mg_campaign_type_client_registry(bool $includeInternal = false): array
{
    return array_values(array_map(
        static fn(array $definition): array => [
            'key' => $definition['key'],
            'label' => $definition['label'],
            'category' => $definition['category'],
            'description' => $definition['description'],
            'public_path' => $definition['public_path'],
            'source_type' => $definition['source_type'],
            'wallet_issue_mode' => $definition['wallet_issue_mode'],
            'default_copy' => $definition['default_copy'],
            'internal_only' => !empty($definition['internal_only']),
            'public_enabled' => !empty($definition['public_enabled']),
            'public_transactional' => mg_campaign_type_public_transactional((string)$definition['key']),
            'public_mode' => mg_campaign_type_public_mode((string)$definition['key']),
        ],
        array_filter(
            mg_campaign_type_registry(),
            static fn(array $definition): bool => $includeInternal || empty($definition['internal_only'])
        )
    ));
}
