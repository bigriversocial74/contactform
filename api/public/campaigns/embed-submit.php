<?php
declare(strict_types=1);

require_once __DIR__ . '/_embed_cors.php';
mg_public_campaign_embed_cors();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
mg_public_campaign_embed_cors();

mg_require_method('POST');
$input = mg_input();
$campaignType = trim((string)($input['campaign_type'] ?? ''));

if (in_array($campaignType, ['watch_video_reward', 'listen_music_reward'], true)) {
    mg_fail('Media reward embeds open the full Microgifter media reward page so watch/listen progress, milestone rewards, Inbox delivery, and PPPM handoff stay accurate.', 409);
}

$target = match ($campaignType) {
    'newsletter_signup' => 'signup.php',
    'contest_giveaway' => 'contest-entry.php',
    'qr_reward_drop' => 'qr-pickup.php',
    'survey_feedback_reward' => 'survey-feedback.php',
    'referral_reward', 'birthday_vip', 'agent_offer' => 'engage.php',
    default => 'engage.php',
};

require __DIR__ . '/' . $target;