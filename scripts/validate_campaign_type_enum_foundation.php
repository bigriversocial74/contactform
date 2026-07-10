<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$registryPath = $root . '/includes/campaign-types.php';
$migrationPath = $root . '/database/campaign_type_enum_foundation_20260709.sql';
$manifestPath = $root . '/config/migrations.php';
$campaignApiPath = $root . '/api/merchant/campaigns.php';
$campaignCorePath = $root . '/api/merchant/campaigns-core.php';

$registry = is_file($registryPath) ? (string)file_get_contents($registryPath) : '';
$migration = is_file($migrationPath) ? (string)file_get_contents($migrationPath) : '';
$manifest = is_file($manifestPath) ? (string)file_get_contents($manifestPath) : '';
$campaignApi = (is_file($campaignApiPath) ? (string)file_get_contents($campaignApiPath) : '')
    . "\n"
    . (is_file($campaignCorePath) ? (string)file_get_contents($campaignCorePath) : '');

$campaignTypes = [
    'newsletter_signup','contest_giveaway','qr_reward_drop','referral_reward','birthday_vip','agent_offer',
    'survey_feedback_reward','check_in_reward','instant_win_reward','stamp_card_reward','rsvp_event_reward',
    'watch_video_reward','listen_music_reward','customer_refund',
];

$contactSources = [
    'newsletter_signup','contest_entry','qr_scan','referral','birthday_vip','agent_discovery','survey_feedback',
    'check_in_reward','instant_win_reward','stamp_card_reward','rsvp_event_reward','watch_video_reward',
    'listen_music_reward','customer_refund','manual','api_issue',
];

$walletSources = [
    'purchase','manual_send','newsletter_signup','contest_entry','contest_winner','qr_scan','referral','birthday_vip',
    'agent_discovery','survey_feedback','survey_feedback_reward','check_in_reward','instant_win_reward',
    'stamp_card_reward','rsvp_event_reward','watch_video_reward','listen_music_reward','customer_refund','api_issue',
];

$checks = [];
$failures = [];
$assert = static function (string $label, bool $passed) use (&$checks, &$failures): void {
    $checks[] = [$label, $passed];
    if (!$passed) $failures[] = $label;
};

$assert('Campaign type registry exists', str_contains($registry, 'function mg_campaign_type_registry'));
$assert('Migration file exists', $migration !== '');
$assert('Migration modifies campaigns.campaign_type', str_contains($migration, 'MODIFY campaign_type ENUM'));
$assert('Migration modifies campaign_contacts.source', str_contains($migration, 'MODIFY source ENUM'));
$assert('Migration modifies wallet_items.source_type', str_contains($migration, 'MODIFY source_type ENUM'));
$assert('Migration is in canonical manifest', str_contains($manifest, 'campaign_type_enum_foundation_20260709.sql'));
$assert('Campaign save API validates against registry', str_contains($campaignApi, 'mg_campaign_type_is_valid'));

foreach ($campaignTypes as $type) {
    $assert('Registry includes campaign type ' . $type, str_contains($registry, "'" . $type . "'"));
    $assert('Migration campaign enum includes ' . $type, str_contains($migration, "'" . $type . "'"));
}
foreach ($contactSources as $source) {
    $assert('Migration contact source enum includes ' . $source, str_contains($migration, "'" . $source . "'"));
}
foreach ($walletSources as $source) {
    $assert('Migration wallet source enum includes ' . $source, str_contains($migration, "'" . $source . "'"));
}

foreach ($checks as [$label, $passed]) echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
if ($failures) {
    echo PHP_EOL . 'Campaign type enum foundation validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}
echo PHP_EOL . 'Campaign type enum foundation validation passed.' . PHP_EOL;
