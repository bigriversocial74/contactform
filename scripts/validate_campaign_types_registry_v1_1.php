<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$failures = [];

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (string)file_get_contents($full) : '';
};

$assert = static function (string $label, bool $passed) use (&$checks, &$failures): void {
    $checks[] = [$label, $passed];
    if (!$passed) $failures[] = $label;
};

$registry = $read('includes/campaign-types.php');
$view = $read('includes/merchant-campaigns-view.php');
$js = $read('assets/js/stage12-campaigns.js');
$api = $read('api/merchant/campaigns.php');
$activity = $read('api/merchant/campaign-activity.php');
$publicPage = $read('includes/public-campaign-page.php');
$engage = $read('api/public/campaigns/engage.php');
$sql = $read('database/campaign_types_registry_v1_1.sql');
$stage12 = $read('database/stage_12_campaigns_reward_templates.sql');
$fullImport = $read('database/stage_12_campaign_features_full_import.sql');

$types = ['newsletter_signup','contest_giveaway','qr_reward_drop','referral_reward','birthday_vip','agent_offer','customer_refund'];
foreach ($types as $type) {
    $assert("Registry defines {$type}", str_contains($registry, "'{$type}' =>") || str_contains($registry, "'{$type}'"));
}
$assert('Registry exposes public option helpers', str_contains($registry, 'function mg_campaign_type_options') && str_contains($registry, 'function mg_campaign_type_client_registry'));
$assert('Registry exposes validation helpers', str_contains($registry, 'function mg_campaign_type_is_valid') && str_contains($registry, 'function mg_campaign_type_requires_reward_template'));
$assert('Registry marks customer_refund internal only', str_contains($registry, "'customer_refund'") && str_contains($registry, "'internal_only' => true") && str_contains($registry, "'public_enabled' => false"));
$assert('Registry defines customer_refund source', str_contains($registry, "'source_type' => 'customer_refund'"));

$assert('Merchant campaign view requires registry', str_contains($view, "require_once __DIR__ . '/campaign-types.php'"));
$assert('Merchant campaign view renders options from registry', str_contains($view, 'mg_campaign_type_options(false)') && str_contains($view, 'foreach ($mgCampaignTypeOptions as $typeOption)'));
$assert('Merchant campaign view exposes client registry', str_contains($view, 'window.MicrogifterCampaignTypes') && str_contains($view, 'mg_campaign_type_client_registry(false)'));

$assert('Builder JS reads registry payload', str_contains($js, 'window.MicrogifterCampaignTypes'));
$assert('Builder JS maps registry labels', str_contains($js, 'function campaignTypeLabel') && str_contains($js, 'registryMap'));
$assert('Builder JS maps registry public paths', str_contains($js, 'function campaignTypePath'));
$assert('Builder JS still keeps fallback defaults', str_contains($js, 'campaignDefaults') && str_contains($js, 'newsletter_signup'));

$assert('Merchant campaigns API requires registry', str_contains($api, '/includes/campaign-types.php'));
$assert('Merchant campaigns API validates through registry', str_contains($api, 'mg_campaign_type_is_valid($campaignType, true)'));
$assert('Merchant campaigns API uses registry reward requirement', str_contains($api, 'mg_campaign_type_requires_reward_template'));
$assert('Merchant campaigns API supports customer_refund rules', str_contains($api, "\$campaignType === 'customer_refund'") && str_contains($api, "'merchant_initiated'"));
$assert('Merchant campaigns API blocks public slug for internal types', str_contains($api, 'mg_campaign_type_public_enabled($campaignType) ? mg_campaign_unique_slug'));
$assert('Merchant campaigns API returns campaign type metadata', str_contains($api, 'campaign_type_label') && str_contains($api, 'campaign_type_category') && str_contains($api, "'campaign_types' => mg_campaign_type_options(true)"));

$assert('Activity API requires registry', str_contains($activity, '/includes/campaign-types.php'));
$assert('Activity API public URLs use registry', str_contains($activity, 'mg_campaign_type_public_enabled') && str_contains($activity, 'mg_campaign_type_public_path'));
$assert('Activity API returns type metadata', str_contains($activity, 'campaign_type_label') && str_contains($activity, 'internal_only'));

$assert('Public campaign page requires registry', str_contains($publicPage, '/campaign-types.php'));
$assert('Public campaign page labels through registry', str_contains($publicPage, 'mg_campaign_type_label($type)'));
$assert('Public campaign page endpoints through registry', str_contains($publicPage, 'mg_campaign_type_submit_endpoint($type)'));
$assert('Public campaign page blocks internal-only types', str_contains($publicPage, '!mg_campaign_type_public_enabled'));

$assert('Generic public engage requires registry', str_contains($engage, '/includes/campaign-types.php'));
$assert('Generic public engage source uses registry', str_contains($engage, 'mg_campaign_type_source($campaignType)'));
$assert('Generic public engage blocks internal-only types', str_contains($engage, '!mg_campaign_type_public_enabled($campaignType)'));
$assert('Generic public engage event uses registry', str_contains($engage, 'mg_campaign_type_event_type($campaignType)'));

$assert('v1.1 SQL migration exists', $sql !== '');
$assert('v1.1 SQL adds customer_refund campaign enum', str_contains($sql, "'customer_refund'"));
$assert('v1.1 SQL aligns campaign contact source enum', str_contains($sql, 'ALTER TABLE campaign_contacts') && str_contains($sql, "'referral'") && str_contains($sql, "'birthday_vip'") && str_contains($sql, "'customer_refund'"));
$assert('v1.1 SQL aligns wallet source enum', str_contains($sql, 'ALTER TABLE wallet_items') && str_contains($sql, "'referral'") && str_contains($sql, "'birthday_vip'") && str_contains($sql, "'customer_refund'"));

$assert('Stage 12 schema includes customer_refund', str_contains($stage12, "'customer_refund'"));
$assert('Stage 12 full import includes customer_refund', str_contains($fullImport, "'customer_refund'"));
$assert('Stage 12 schemas include referral/birthday wallet source', str_contains($stage12, "'referral'") && str_contains($stage12, "'birthday_vip'") && str_contains($fullImport, "'referral'") && str_contains($fullImport, "'birthday_vip'"));

foreach ($checks as [$label, $passed]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}

if ($failures) {
    echo PHP_EOL . 'Campaign Types v1.1 registry validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Campaign Types v1.1 registry validation passed.' . PHP_EOL;
