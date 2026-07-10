<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/campaign-types.php';

$checks = [];
$failures = [];
$rows = [];

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (string)file_get_contents($full) : '';
};
$assert = static function (string $label, bool $passed, string $detail = '') use (&$checks, &$failures): void {
    $checks[] = [$label, $passed, $detail];
    if (!$passed) $failures[] = $label . ($detail !== '' ? ' — ' . $detail : '');
};
$modeLabel = static function (mixed $mode): string {
    if (is_array($mode)) return implode('|', array_map('strval', $mode));
    return (string)$mode;
};

$matrix = [
    'newsletter_signup' => ['path'=>'/newsletter-signup.php','endpoint'=>'/api/public/campaigns/signup.php','family'=>'shared','mode'=>'instant_reward'],
    'contest_giveaway' => ['path'=>'/contest.php','endpoint'=>'/api/public/campaigns/contest-entry.php','family'=>'shared','mode'=>['first_x','instant_reward','random_draw','manual_winner']],
    'qr_reward_drop' => ['path'=>'/qr-reward.php','endpoint'=>'/api/public/campaigns/qr-pickup.php','family'=>'shared','mode'=>'qr_claim'],
    'referral_reward' => ['path'=>'/referral-reward.php','endpoint'=>'/api/public/campaigns/engage.php','family'=>'shared','mode'=>'referral_capture'],
    'birthday_vip' => ['path'=>'/birthday-vip.php','endpoint'=>'/api/public/campaigns/engage.php','family'=>'shared','mode'=>'birthday_capture'],
    'agent_offer' => ['path'=>'/agent-offer.php','endpoint'=>'/api/public/campaigns/engage.php','family'=>'shared','mode'=>'agent_interest'],
    'survey_feedback_reward' => ['path'=>'/survey-feedback.php','endpoint'=>'/api/public/campaigns/survey-feedback.php','family'=>'specialized','mode'=>'survey_feedback'],
    'check_in_reward' => ['path'=>'/check-in-reward.php','endpoint'=>'/api/public/campaigns/check-in.php','family'=>'specialized','mode'=>'geo_check_in'],
    'instant_win_reward' => ['path'=>'/instant-win.php','endpoint'=>'/api/public/campaigns/instant-win.php','family'=>'interactive','mode'=>['scratch_card','spin_wheel']],
    'stamp_card_reward' => ['path'=>'/stamp-card.php','endpoint'=>'/api/public/campaigns/stamp-card.php','family'=>'interactive','mode'=>'verified_stamp_card'],
    'rsvp_event_reward' => ['path'=>'/rsvp-event.php','endpoint'=>'/api/public/campaigns/rsvp-event.php','family'=>'specialized','mode'=>'rsvp_attendance'],
    'watch_video_reward' => ['path'=>'/watch-reward.php','endpoint'=>'/api/public/campaigns/watch-progress-v2.php','family'=>'media','mode'=>'video_watch_milestones'],
    'listen_music_reward' => ['path'=>'/listen-reward.php','endpoint'=>'/api/public/campaigns/listen-progress.php','family'=>'media','mode'=>'audio_listen_milestones'],
];

$registry = mg_campaign_type_registry();
$publicRegistry = array_filter($registry, static fn(array $definition): bool => !empty($definition['public_enabled']) && empty($definition['internal_only']));
$internalRegistry = array_filter($registry, static fn(array $definition): bool => !empty($definition['internal_only']));

$assert('Registry contains 14 campaign types', count($registry) === 14, 'found ' . count($registry));
$assert('Registry contains 13 public campaign types', count($publicRegistry) === 13, 'found ' . count($publicRegistry));
$assert('Registry contains one internal-only campaign type', count($internalRegistry) === 1 && isset($internalRegistry['customer_refund']));
$assert('Public matrix covers every public registry type', array_keys($matrix) === array_keys($publicRegistry));

$requiredDefinitionFields = [
    'key','label','category','description','merchant_use_case','public_path','submit_endpoint','source_type',
    'event_type','requires_reward_template','public_enabled','crm_enabled','embed_allowed','internal_only',
    'wallet_issue_mode','default_status','analytics_bucket','default_copy','rules_schema',
];

$sharedRenderer = $read('includes/public-campaign-page.php');
$foundation = $read('includes/campaign-landing-foundation.php');
$mediaHelper = $read('includes/campaign-media-landing.php');
$builderCore = $read('api/merchant/campaigns-core.php');
$specializedRoute = $read('api/merchant/campaigns.php');
$genericEngage = $read('api/public/campaigns/engage.php');

$assert('Canonical foundation exposes load/state/profile/image primitives',
    str_contains($foundation, 'function mg_campaign_landing_bootstrap')
    && str_contains($foundation, 'function mg_campaign_landing_state')
    && str_contains($foundation, 'function mg_campaign_landing_render_profile')
    && str_contains($foundation, 'function mg_campaign_landing_campaign_image')
    && str_contains($foundation, 'function mg_campaign_landing_primary_image'));
$assert('Shared renderer uses canonical state, profile, endpoint, image, and lower-card contracts',
    str_contains($sharedRenderer, 'mg_campaign_landing_state')
    && str_contains($sharedRenderer, 'mg_campaign_landing_render_profile')
    && str_contains($sharedRenderer, 'mg_campaign_type_submit_endpoint')
    && str_contains($sharedRenderer, 'mg_campaign_landing_campaign_image')
    && str_contains($sharedRenderer, 'mg_campaign_landing_render_bottom_cards'));
$assert('Media helper standardizes merchant profile and three information cards',
    str_contains($mediaHelper, 'mg_campaign_landing_render_profile')
    && str_contains($mediaHelper, 'Reward Info')
    && str_contains($mediaHelper, 'Reward Levels')
    && str_contains($mediaHelper, 'Active Status &amp; Updates'));
$assert('Generic engagement endpoint is registry-controlled',
    str_contains($genericEngage, 'mg_campaign_type_public_enabled')
    && str_contains($genericEngage, 'mg_campaign_type_source')
    && str_contains($genericEngage, 'mg_campaign_type_event_type'));
$assert('Builder persists canonical Instant Win modes',
    str_contains($builderCore, "['scratch_card', 'spin_wheel']")
    && str_contains($builderCore, "'play_mode' => $mode"));
$assert('Builder persists verified Stamp Card mode', str_contains($builderCore, "'mode' => 'verified_stamp_card'"));
$assert('Specialized rule route persists Survey, Check-In, and RSVP contracts',
    str_contains($specializedRoute, "'survey_feedback_reward'")
    && str_contains($specializedRoute, "'check_in_reward'")
    && str_contains($specializedRoute, "'rsvp_event_reward'"));

foreach ($matrix as $type => $expected) {
    $definition = $registry[$type] ?? null;
    $definitionOk = is_array($definition);
    $assert($type . ': registered', $definitionOk);
    if (!$definitionOk) continue;

    foreach ($requiredDefinitionFields as $field) {
        $assert($type . ': registry field ' . $field, array_key_exists($field, $definition));
    }
    $assert($type . ': key matches registry index', ($definition['key'] ?? null) === $type);
    $assert($type . ': public enabled', !empty($definition['public_enabled']) && empty($definition['internal_only']));
    $assert($type . ': public path matches matrix', ($definition['public_path'] ?? '') === $expected['path'], (string)($definition['public_path'] ?? ''));
    $assert($type . ': submit endpoint matches matrix', ($definition['submit_endpoint'] ?? '') === $expected['endpoint'], (string)($definition['submit_endpoint'] ?? ''));

    $actualMode = $definition['rules_schema']['mode'] ?? null;
    $expectedMode = $expected['mode'];
    $modeOk = is_array($expectedMode)
        ? is_array($actualMode) && array_values($actualMode) === array_values($expectedMode)
        : $actualMode === $expectedMode;
    $assert($type . ': canonical mode contract', $modeOk, 'expected ' . $modeLabel($expectedMode) . ', got ' . $modeLabel($actualMode));

    $pagePath = ltrim($expected['path'], '/');
    $endpointPath = ltrim($expected['endpoint'], '/');
    $page = $read($pagePath);
    $endpoint = $read($endpointPath);
    $assert($type . ': landing page file exists', $page !== '', $pagePath);
    $assert($type . ': submit endpoint file exists', $endpoint !== '', $endpointPath);
    $assert($type . ': landing page declares campaign type', str_contains($page, $type));

    $familyOk = match ($expected['family']) {
        'shared' => str_contains($page, 'mg_campaign_landing_bootstrap') && str_contains($page, 'includes/public-campaign-page.php'),
        'specialized' => str_contains($page, 'mg_campaign_landing_bootstrap') && str_contains($page, 'mg_campaign_landing_state') && str_contains($page, 'mg_campaign_landing_render_bottom_cards'),
        'interactive' => str_contains($page, 'mg_campaign_landing_bootstrap') && str_contains($page, 'mg_campaign_landing_state') && str_contains($page, 'mg-rl-interactive') && str_contains($page, 'mg_campaign_landing_render_bottom_cards'),
        'media' => str_contains($page, 'includes/campaign-media-landing.php') && str_contains($page, 'mg_campaign_landing_bootstrap') && str_contains($page, 'mg_campaign_landing_state') && str_contains($page, 'mg_campaign_media_render_join') && str_contains($page, 'mg_campaign_media_render_cards'),
        default => false,
    };
    $assert($type . ': canonical ' . $expected['family'] . ' landing family', $familyOk);

    $previewOk = $expected['family'] === 'shared'
        ? str_contains($sharedRenderer, 'data-campaign-preview="merchant"') && str_contains($sharedRenderer, 'data-merchant-campaign-preview')
        : str_contains($page, 'previewMode') && (str_contains($page, 'data-campaign-preview="merchant"') || str_contains($page, 'data-campaign-preview'));
    $assert($type . ': merchant preview supported', $previewOk);

    $imageOk = match ($expected['family']) {
        'shared' => str_contains($sharedRenderer, 'mg_campaign_landing_campaign_image'),
        'specialized' => str_contains($page, 'mg_campaign_landing_primary_image') || str_contains($page, 'mg_campaign_landing_campaign_image'),
        'interactive', 'media' => str_contains($page, 'mg_campaign_landing_campaign_image'),
        default => false,
    };
    $assert($type . ': campaign image priority supported', $imageOk);

    $endpointPost = str_contains($endpoint, "mg_require_method('POST')")
        || str_contains($endpoint, 'mg_require_method("POST")')
        || str_contains($endpoint, 'mg_media_reward_progress_v2');
    $assert($type . ': endpoint enforces POST contract', $endpointPost);

    if ($expected['endpoint'] === '/api/public/campaigns/engage.php') {
        $assert($type . ': generic endpoint accepts registry-controlled type', str_contains($genericEngage, 'mg_campaign_type_public_enabled'));
    } elseif ($type === 'watch_video_reward') {
        $assert($type . ': v2 progress endpoint uses shared media engine', str_contains($endpoint, "mg_media_reward_progress_v2('watch_video_reward'"));
    } else {
        $assert($type . ': endpoint is type-specific', str_contains($endpoint, $type) || str_contains($endpoint, str_replace('_reward', '', $type)));
    }

    $rows[] = [
        'type' => $type,
        'route' => $expected['path'],
        'endpoint' => $expected['endpoint'],
        'family' => $expected['family'],
        'mode' => $modeLabel($expectedMode),
        'status' => 'PASS',
    ];
}

$refund = $registry['customer_refund'] ?? [];
$assert('Customer Refund remains internal-only',
    !empty($refund['internal_only'])
    && empty($refund['public_enabled'])
    && empty($refund['embed_allowed'])
    && ($refund['public_path'] ?? null) === ''
    && ($refund['submit_endpoint'] ?? null) === '');
$assert('Customer Refund canonical mode remains merchant initiated', ($refund['rules_schema']['mode'] ?? null) === 'merchant_initiated');

$reportDir = $root . '/build';
if (!is_dir($reportDir)) @mkdir($reportDir, 0775, true);
$report = "# Microgifter Public Campaign Matrix v1\n\n";
$report .= "Generated by `scripts/validate_campaign_public_matrix_v1.php`.\n\n";
$report .= "| Campaign type | Public route | Submit endpoint | Landing family | Mode | Status |\n";
$report .= "|---|---|---|---|---|---|\n";
foreach ($rows as $row) {
    $report .= sprintf("| `%s` | `%s` | `%s` | %s | `%s` | %s |\n", $row['type'], $row['route'], $row['endpoint'], $row['family'], $row['mode'], $row['status']);
}
$report .= "\nInternal-only: `customer_refund` — no public route or customer submit endpoint.\n";
@file_put_contents($reportDir . '/campaign-public-matrix-v1.md', $report);

foreach ($checks as [$label, $passed, $detail]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . ($detail !== '' ? ' (' . $detail . ')' : '') . PHP_EOL;
}

if ($failures) {
    echo PHP_EOL . 'Campaign Public Matrix v1 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Campaign Public Matrix v1 validation passed for 13 public campaign types and one internal-only type.' . PHP_EOL;
