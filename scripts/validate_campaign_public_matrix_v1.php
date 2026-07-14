<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/campaign-types.php';

$failures = [];
$checks = 0;
$rows = [];
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (string)file_get_contents($full) : '';
};
$check = static function (bool $passed, string $label, string $detail = '') use (&$failures, &$checks): void {
    $checks++;
    if (!$passed) $failures[] = $label . ($detail !== '' ? ' — ' . $detail : '');
};
$modeText = static fn(mixed $mode): string => is_array($mode) ? implode('|', array_map('strval', $mode)) : (string)$mode;

$matrix = [
    'newsletter_signup' => ['/newsletter-signup.php','/api/public/campaigns/signup.php','shared','instant_reward'],
    'contest_giveaway' => ['/contest.php','/api/public/campaigns/contest-entry.php','shared',['first_x','instant_reward','random_draw','manual_winner']],
    'qr_reward_drop' => ['/qr-reward.php','/api/public/campaigns/qr-pickup.php','shared','qr_claim'],
    'referral_reward' => ['/referral-reward.php','/api/public/campaigns/engage.php','shared','referral_capture'],
    'birthday_vip' => ['/birthday-vip.php','/api/public/campaigns/engage.php','shared','birthday_capture'],
    'agent_offer' => ['/agent-offer.php','/api/public/campaigns/engage.php','shared','agent_interest'],
    'survey_feedback_reward' => ['/survey-feedback.php','/api/public/campaigns/survey-feedback.php','specialized','survey_feedback'],
    'check_in_reward' => ['/check-in-reward.php','/api/public/campaigns/check-in.php','specialized','geo_check_in'],
    'instant_win_reward' => ['/instant-win.php','/api/public/campaigns/instant-win.php','interactive',['scratch_card','spin_wheel']],
    'stamp_card_reward' => ['/stamp-card.php','/api/public/campaigns/stamp-card.php','interactive','verified_stamp_card'],
    'rsvp_event_reward' => ['/rsvp-event.php','/api/public/campaigns/rsvp-event.php','specialized','rsvp_attendance'],
    'watch_video_reward' => ['/watch-reward.php','/api/public/campaigns/watch-progress-v2.php','media','video_watch_milestones'],
    'listen_music_reward' => ['/listen-reward.php','/api/public/campaigns/listen-progress.php','media','audio_listen_milestones'],
    'loyalty_quest' => ['/loyalty-quest.php','/api/public/loyalty-quest/submit.php','quest','verified_loyalty_quest'],
];

$registry = mg_campaign_type_registry();
$public = array_filter($registry, static fn(array $definition): bool => !empty($definition['public_enabled']) && empty($definition['internal_only']));
$internal = array_filter($registry, static fn(array $definition): bool => !empty($definition['internal_only']));
$check(count($registry) === 15, 'Registry contains 15 campaign types', 'found ' . count($registry));
$check(count($public) === 14, 'Registry contains 14 public campaign types', 'found ' . count($public));
$check(array_keys($public) === array_keys($matrix), 'Public registry matches the 14-type matrix');
$check(count($internal) === 1 && isset($internal['customer_refund']), 'Customer Refund is the only internal campaign type');

$foundation = $read('includes/campaign-landing-foundation.php');
$shared = $read('includes/public-campaign-page.php');
$media = $read('includes/campaign-media-landing.php');
$builder = $read('api/merchant/campaigns-core.php');
$specialized = $read('api/merchant/campaigns.php');
$engage = $read('api/public/campaigns/engage.php');
$questManager = $read('assets/js/merchant-loyalty-quests.js');

$check(str_contains($foundation, 'function mg_campaign_landing_bootstrap')
    && str_contains($foundation, 'function mg_campaign_landing_state')
    && str_contains($foundation, 'function mg_campaign_landing_render_profile')
    && str_contains($foundation, 'function mg_campaign_landing_campaign_image'), 'Canonical campaign landing primitives exist');
$check(str_contains($shared, 'mg_campaign_type_submit_endpoint')
    && str_contains($shared, 'mg_campaign_landing_render_bottom_cards'), 'Shared landing renderer uses registry endpoints and foundation cards');
$check(str_contains($media, 'mg_campaign_media_render_join')
    && str_contains($media, 'mg_campaign_media_render_cards')
    && str_contains($media, 'Active Status &amp; Updates'), 'Media landing helper exposes the standardized profile, form, and cards');
$check(str_contains($builder, "['scratch_card', 'spin_wheel']")
    && str_contains($builder, "'play_mode' => " . '$mode'), 'Builder persists canonical Instant Win modes');
$check(str_contains($builder, "'mode' => 'verified_stamp_card'"), 'Builder persists verified Stamp Card mode');
$check(str_contains($specialized, "'survey_feedback_reward'")
    && str_contains($specialized, "'check_in_reward'")
    && str_contains($specialized, "'rsvp_event_reward'"), 'Specialized rule route covers Survey, Check-In, and RSVP');
$check(str_contains($engage, 'mg_campaign_type_public_enabled')
    && str_contains($engage, 'mg_campaign_type_source')
    && str_contains($engage, 'mg_campaign_type_event_type'), 'Generic engagement endpoint is registry controlled');

$requiredFields = ['key','label','category','description','merchant_use_case','public_path','submit_endpoint','source_type','event_type','public_enabled','internal_only','wallet_issue_mode','default_copy','rules_schema'];
foreach ($matrix as $type => [$route, $submit, $family, $expectedMode]) {
    $definition = $registry[$type] ?? null;
    $check(is_array($definition), $type . ': registry definition exists');
    if (!is_array($definition)) continue;

    foreach ($requiredFields as $field) $check(array_key_exists($field, $definition), $type . ': registry field ' . $field);
    $check(($definition['key'] ?? '') === $type, $type . ': registry key matches index');
    $check(!empty($definition['public_enabled']) && empty($definition['internal_only']), $type . ': public flags are valid');
    $check(($definition['public_path'] ?? '') === $route, $type . ': public route matches matrix', (string)($definition['public_path'] ?? ''));
    $check(($definition['submit_endpoint'] ?? '') === $submit, $type . ': submit endpoint matches matrix', (string)($definition['submit_endpoint'] ?? ''));

    $actualMode = $definition['rules_schema']['mode'] ?? null;
    $modeMatches = is_array($expectedMode)
        ? is_array($actualMode) && array_values($actualMode) === array_values($expectedMode)
        : $actualMode === $expectedMode;
    $check($modeMatches, $type . ': canonical mode matches matrix', 'expected ' . $modeText($expectedMode) . ', got ' . $modeText($actualMode));

    $pagePath = ltrim($route, '/');
    $submitPath = ltrim($submit, '/');
    $page = $read($pagePath);
    $endpoint = $read($submitPath);
    $check($page !== '', $type . ': landing page exists', $pagePath);
    $check($endpoint !== '', $type . ': submit endpoint exists', $submitPath);
    $declaresType = $type === 'loyalty_quest'
        ? str_contains($page, 'data-loyalty-quest-participant') && str_contains($page, 'data-campaign-ref')
        : str_contains($page, $type);
    $check($declaresType, $type . ': landing page declares its campaign type');

    $interactiveCards = str_contains($page, 'mg_campaign_landing_render_bottom_cards')
        || (str_contains($page, 'data-campaign-foundation-cards') && str_contains($page, 'mg-stamp-summary-card'));
    $familyMatches = match ($family) {
        'shared' => str_contains($page, 'mg_campaign_landing_bootstrap') && str_contains($page, 'includes/public-campaign-page.php'),
        'specialized' => str_contains($page, 'mg_campaign_landing_bootstrap') && str_contains($page, 'mg_campaign_landing_state') && str_contains($page, 'mg_campaign_landing_render_bottom_cards'),
        'interactive' => str_contains($page, 'mg_campaign_landing_bootstrap') && str_contains($page, 'mg-rl-interactive') && $interactiveCards,
        'media' => str_contains($page, 'includes/campaign-media-landing.php') && str_contains($page, 'mg_campaign_media_render_join') && str_contains($page, 'mg_campaign_media_render_cards'),
        'quest' => str_contains($page, 'data-loyalty-quest-participant') && str_contains($page, 'data-lqp-start') && str_contains($page, 'data-lqp-proof-form'),
        default => false,
    };
    $check($familyMatches, $type . ': canonical ' . $family . ' landing family');

    $previewSupported = match ($family) {
        'shared' => str_contains($shared, 'data-campaign-preview="merchant"') && str_contains($shared, 'data-merchant-campaign-preview'),
        'quest' => str_contains($questManager, 'Open public page') && str_contains($questManager, 'public_url'),
        default => str_contains($page, 'previewMode') && str_contains($page, 'data-campaign-preview'),
    };
    $check($previewSupported, $type . ': merchant preview supported');

    $imageSupported = match ($family) {
        'shared' => str_contains($shared, 'mg_campaign_landing_campaign_image'),
        'specialized' => str_contains($page, 'mg_campaign_landing_primary_image') || str_contains($page, 'mg_campaign_landing_campaign_image'),
        'interactive', 'media' => str_contains($page, 'mg_campaign_landing_campaign_image'),
        'quest' => str_contains($page, 'data-lqp-image') && str_contains($page, 'loyalty-quest-placeholder.svg'),
        default => false,
    };
    $check($imageSupported, $type . ': campaign image priority supported');

    $routesToEngage = str_contains($endpoint, "require __DIR__ . '/engage.php'")
        && (str_contains($engage, "mg_require_method('POST')") || str_contains($engage, 'mg_require_method("POST")'));
    $postProtected = str_contains($endpoint, "mg_require_method('POST')")
        || str_contains($endpoint, 'mg_require_method("POST")')
        || str_contains($endpoint, 'mg_media_reward_progress_v2')
        || $routesToEngage;
    $check($postProtected, $type . ': endpoint enforces POST contract');

    if ($submit === '/api/public/campaigns/engage.php') {
        $check(str_contains($engage, 'mg_campaign_type_public_enabled'), $type . ': generic endpoint validates registry access');
    } elseif ($type === 'watch_video_reward') {
        $check(str_contains($endpoint, "mg_media_reward_progress_v2('watch_video_reward'"), $type . ': v2 endpoint uses shared media engine');
    } elseif ($type === 'loyalty_quest') {
        $check(str_contains($endpoint, 'mg_require_api_user') && str_contains($endpoint, 'mg_require_csrf_for_write') && str_contains($endpoint, 'mg_lqv_resolve'), $type . ': participant endpoint enforces identity, CSRF, and verification authority');
    }

    $rows[] = [$type, $route, $submit, $family, $modeText($expectedMode)];
}

$refund = $registry['customer_refund'] ?? [];
$check(!empty($refund['internal_only'])
    && empty($refund['public_enabled'])
    && empty($refund['embed_allowed'])
    && ($refund['public_path'] ?? null) === ''
    && ($refund['submit_endpoint'] ?? null) === '', 'Customer Refund exposes no public route or submit endpoint');
$check(($refund['rules_schema']['mode'] ?? null) === 'merchant_initiated', 'Customer Refund keeps merchant-initiated mode');

$build = $root . '/build';
if (!is_dir($build)) @mkdir($build, 0775, true);
$report = "# Microgifter Public Campaign Matrix v1\n\n";
$report .= "Generated by `scripts/validate_campaign_public_matrix_v1.php`.\n\n";
$report .= "| Campaign type | Public route | Submit endpoint | Landing family | Mode | Status |\n|---|---|---|---|---|---|\n";
foreach ($rows as [$type, $route, $submit, $family, $mode]) {
    $report .= "| `{$type}` | `{$route}` | `{$submit}` | {$family} | `{$mode}` | PASS |\n";
}
$report .= "\nInternal-only: `customer_refund` — no public route or submit endpoint.\n";
@file_put_contents($build . '/campaign-public-matrix-v1.md', $report);

if ($failures) {
    echo 'Campaign Public Matrix v1 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    echo count($failures) . ' of ' . $checks . ' checks failed.' . PHP_EOL;
    exit(1);
}

echo 'Campaign Public Matrix v1 validation passed: ' . $checks . ' checks across 14 public campaign types and one internal-only type.' . PHP_EOL;
