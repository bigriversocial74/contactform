<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/campaign-types.php';

$failures = [];
$passes = [];

$check = static function (string $label, bool $condition) use (&$failures, &$passes): void {
    if ($condition) {
        $passes[] = $label;
        echo "[PASS] {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "[FAIL] {$label}\n";
};

$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . ltrim($path, '/'));
    if (!is_string($contents)) throw new RuntimeException('Unable to read ' . $path);
    return $contents;
};

$expectedTypes = [
    'newsletter_signup',
    'contest_giveaway',
    'qr_reward_drop',
    'referral_reward',
    'birthday_vip',
    'agent_offer',
    'survey_feedback_reward',
    'check_in_reward',
    'instant_win_reward',
    'stamp_card_reward',
    'rsvp_event_reward',
    'watch_video_reward',
    'listen_music_reward',
    'customer_refund',
    'loyalty_quest',
    'public_donation',
];

$registry = mg_campaign_type_registry();
$registryKeys = array_keys($registry);
$missingTypes = array_values(array_diff($expectedTypes, $registryKeys));
$unexpectedTypes = array_values(array_diff($registryKeys, $expectedTypes));

$check('1. Canonical registry contains the exact 16 campaign types', count($registry) === 16 && $missingTypes === [] && $unexpectedTypes === []);

$definitionsComplete = true;
foreach ($registry as $key => $definition) {
    $definitionsComplete = $definitionsComplete
        && is_array($definition)
        && (string)($definition['key'] ?? '') === $key
        && trim((string)($definition['label'] ?? '')) !== ''
        && trim((string)($definition['category'] ?? '')) !== ''
        && array_key_exists('internal_only', $definition)
        && array_key_exists('public_enabled', $definition)
        && array_key_exists('wallet_issue_mode', $definition);
}
$check('2. Every campaign definition exposes the shared behavioral contract', $definitionsComplete);

$publicRoutesReady = true;
$submitRoutesReady = true;
foreach ($registry as $key => $definition) {
    if (!empty($definition['public_enabled'])) {
        $path = trim((string)($definition['public_path'] ?? ''));
        $publicRoutesReady = $publicRoutesReady && $path !== '' && is_file($root . '/' . ltrim($path, '/'));
    }
    if (mg_campaign_type_public_transactional($key)) {
        $endpoint = mg_campaign_type_submit_endpoint($key);
        $submitRoutesReady = $submitRoutesReady && $endpoint !== '' && is_file($root . '/' . ltrim($endpoint, '/'));
    }
}
$check('3. Every public campaign type has an existing landing page', $publicRoutesReady);
$check('4. Every transactional campaign type has an existing submit endpoint', $submitRoutesReady);

$createMenu = $read('includes/header-templates/create-menu.php');
$createRuntime = $read('assets/js/create-center-inline.js');
$campaignApi = $read('api/merchant/campaigns.php');
$campaignCore = $read('api/merchant/campaigns-core.php');
$check(
    '5. Create Center uses the canonical Campaign Center registry instead of a six-type list',
    str_contains($createMenu, 'mg_public_donations_campaign_type_options')
    && str_contains($createMenu, 'data-create-campaign-types')
    && str_contains($createMenu, '$mgCreateCampaignTypes as $mgCreateCampaignType')
    && !str_contains($createMenu, '<option value="newsletter_signup">Newsletter signup</option><option value="qr_reward_drop">')
    && str_contains($createRuntime, "MG.get('/api/merchant/campaigns.php?status=all')")
    && str_contains($createRuntime, '.campaign_types || []')
    && str_contains($campaignCore, 'mg_public_donations_campaign_type_options($merchantId, $user, true)')
);
$check(
    '6. Quick-create defers specialized activation requirements to the canonical API',
    !str_contains($createRuntime, 'Choose an active reward template before activating the campaign.')
    && str_contains($campaignCore, 'mg_campaign_requires_reward_template($campaignType, $status)')
    && str_contains($campaignCore, "$campaignType === 'watch_video_reward'")
    && str_contains($campaignCore, "$campaignType === 'listen_music_reward'")
    && str_contains($campaignApi, "require __DIR__ . '/campaigns-core.php'")
);

$watchProgress = $read('api/public/campaigns/watch-progress-v2.php');
$listenProgress = $read('api/public/campaigns/listen-progress.php');
$sharedMedia = $read('api/public/campaigns/_media_progress_v2.php');
$check(
    '7. Watch and Listen share one media progress, CRM, wallet, and Inbox authority',
    str_contains($watchProgress, "mg_media_reward_progress_v2('watch_video_reward'")
    && str_contains($listenProgress, "mg_media_reward_progress_v2('listen_music_reward'")
    && str_contains($listenProgress, "require_once __DIR__ . '/_media_progress_v2.php'")
    && str_contains($sharedMedia, 'mg_zero_reward_issue_from_wallet')
    && str_contains($sharedMedia, 'mg_merchant_crm_record_event')
    && str_contains($sharedMedia, "'pppm_destination'=>'inbox'")
);

$actionCenterApi = $read('api/account/action-center.php');
$actionCenterJs = $read('assets/js/gift-action-center.js');
$check(
    '8. Inbox list responses cannot remain stale behind a newer folder count',
    str_contains($actionCenterApi, 'Cache-Control: private, no-store')
    && str_contains($actionCenterJs, '_mg_fresh=')
    && str_contains($actionCenterJs, 'fetchFolderPayload(folder, 1)')
    && str_contains($actionCenterJs, 'MicrogifterActionCenterContract')
    && str_contains($actionCenterJs, 'the gift rows could not be loaded')
);

$campaignCss = $read('assets/css/public-campaign-layout-cleanup-v1.css');
$check(
    '9. Campaign participation column aligns with the canvas and remains responsive',
    str_contains($campaignCss, 'top: 28px !important;')
    && str_contains($campaignCss, '@media (max-width: 1180px)')
    && str_contains($campaignCss, 'align-self: start !important;')
);
$check(
    '10. Campaign footer colors are deterministic for signed-in and signed-out viewers',
    str_contains($campaignCss, '.mg-site-footer.mg-universal-footer')
    && str_contains($campaignCss, 'a:not(.mg-footer-logo):visited')
    && str_contains($campaignCss, '.mg-footer-cookie-settings')
    && str_contains($campaignCss, 'color: #fff !important;')
);

$customerRefund = $registry['customer_refund'] ?? [];
$check(
    'Internal Customer Refund remains registered but non-public',
    !empty($customerRefund['internal_only'])
    && empty($customerRefund['public_enabled'])
    && trim((string)($customerRefund['public_path'] ?? '')) === ''
);

if ($failures !== []) {
    fwrite(STDERR, "\nCampaign System Repair v1 failed " . count($failures) . " check(s).\n");
    exit(1);
}

echo "\nCampaign System Repair v1: 10/10 certification passed (" . count($passes) . " assertions).\n";
