<?php
declare(strict_types=1);

require_once __DIR__ . '/_embed_cors.php';
mg_public_campaign_embed_cors();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
mg_public_campaign_embed_cors();

mg_require_method('POST');
$input = mg_input();
$campaignType = trim((string)($input['campaign_type'] ?? ''));

$target = match ($campaignType) {
    'newsletter_signup' => 'signup.php',
    'contest_giveaway' => 'contest-entry.php',
    'qr_reward_drop' => 'qr-pickup.php',
    'referral_reward', 'birthday_vip', 'agent_offer' => 'engage.php',
    default => 'engage.php',
};

require __DIR__ . '/' . $target;
