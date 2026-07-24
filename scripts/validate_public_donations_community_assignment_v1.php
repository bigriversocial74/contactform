<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }
$root = dirname(__DIR__);
$read = static function(string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content) || trim($content) === '') throw new RuntimeException('Missing file: ' . $path);
    return $content;
};
$must = static function(string $content, array $needles, string $label): void {
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) throw new RuntimeException($label . ' missing contract: ' . $needle);
    }
};

$domain = $read('includes/public-donations-community-assignments.php');
$endpoint = $read('api/merchant/public-donations-community.php');
$ui = $read('assets/js/public-donations-community-assignments.js');
$styles = $read('assets/css/public-donations-community-assignments.css');
$page = $read('merchant-campaigns.php');
$installer = $read('database/20260724_public_donations_community_v1_single_install.sql');

$must($domain, [
    "community_role.slug='community'",
    "u.status='active'",
    'GROUP_CONCAT(DISTINCT role_all.slug',
    "['community', 'admin', 'super_admin']",
    "['add', 'pause', 'remove', 'reactivate']",
    "SET status='active',reactivated_at=NOW()",
    "SET status='paused',paused_at=NOW()",
    "SET status='removed',removed_at=NOW()",
    'mg_create_notification(',
    'public_donations.community_added',
    'campaign_community_assignments',
], 'assignment domain');
$must($endpoint, [
    "merchant.campaigns.view",
    "merchant.campaigns.manage",
    'mg_require_csrf_for_write',
    'mg_public_donations_is_enabled_for',
    "'public_identity_only' => true",
    "'exact_location_excluded' => true",
    "'private_contact_fields_excluded' => true",
    "'reward_inventory_changed' => false",
], 'merchant endpoint');
$must($ui, [
    'data-community-assignment-tab',
    'data-community-campaign-select',
    'data-community-search-results',
    'data-community-assigned-results',
    "['all', 'active', 'paused', 'removed']",
    "Microgifter.post('/api/merchant/public-donations-community.php'",
    'View public profile',
], 'merchant UI');
$must($styles, ['mg-community-assignment-grid', 'mg-community-badge', 'mg-community-status.is-paused'], 'merchant styles');
$must($page, [
    '/assets/css/public-donations-community-assignments.css?v=1.0.0',
    '/assets/js/public-donations-community-assignments.js?v=1.0.0',
], 'campaign page assets');
$must($installer, [
    'CREATE TABLE IF NOT EXISTS campaign_community_assignments',
    'UNIQUE KEY uq_campaign_community_assignment (campaign_id, community_user_id)',
], 'Phase 1 assignment schema');

if (preg_match('/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:wallet_items|reward_templates|campaign_donation_rewards|campaign_donation_reward_events)\b/i', $domain . "\n" . $endpoint) === 1) {
    throw new RuntimeException('Phase 3 must not mutate reward or wallet inventory.');
}
if (str_contains($ui, '.innerHTML')) throw new RuntimeException('Community UI must not inject HTML strings.');
if (preg_match('/\b(?:email|phone|exact_address|private_notes)\b/i', $endpoint) === 1) {
    throw new RuntimeException('Community endpoint exposes a forbidden private field contract.');
}
if (!str_contains($domain, "if ($currentStatus !== 'active')")) {
    throw new RuntimeException('Duplicate active assignments must remain idempotent.');
}

echo "Public Donations Community assignment contracts valid.\n";
