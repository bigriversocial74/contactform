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

$engine = $read('includes/public-donations-allocation.php');
$endpoint = $read('api/merchant/public-donations-allocation.php');
$ui = $read('assets/js/public-donations-allocation.js');
$styles = $read('assets/css/public-donations-allocation.css');
$page = $read('merchant-campaigns.php');
$installer = $read('database/20260724_public_donations_community_v1_single_install.sql');
$bridge = $read('api/rewards/_wallet_pppm_bridge.php');

$must($engine, [
    'mg_public_donations_allocation_recipients',
    'count($normalized) > 50',
    '$total > 1000',
    '$quantity >= 100 || $totalValue >= 100000',
    "'single'",
    "'same_quantity'",
    "'custom_quantity'",
    'beginTransaction()',
    'FOR UPDATE',
    'hash_equals',
    'idempotency_key',
    'request_hash',
    "'public_donation'",
    'mg_zero_reward_issue_from_wallet',
    'campaign_donation_operations',
    'campaign_donation_batches',
    'campaign_donation_rewards',
    'last_allocated_at=NOW()',
    'issued_count=issued_count+?',
    "'allow_self' => true",
    'rollBack()',
], 'allocation engine');
$must($endpoint, [
    'merchant.campaigns.view',
    'merchant.campaigns.manage',
    'mg_require_csrf_for_write',
    "['preflight', 'allocate']",
    "'public_purchase' => false",
    "'max_recipients' => 50",
    "'max_units' => 1000",
    'mg_public_donations_allocation_execute',
], 'allocation endpoint');
$must($ui, [
    'Preview allocation',
    'Allocate rewards',
    'Same quantity',
    'Custom quantities',
    'data-recipient-check',
    'confirm_large_operation',
    "Microgifter.post('/api/merchant/public-donations-allocation.php'",
    'Wallet',
    'PPPM',
    'Microgift',
    'Inbox',
], 'allocation UI');
$must($styles, ['mg-donation-allocation-card', 'mg-donation-preflight-metrics', 'mg-donation-large-confirmation'], 'allocation styles');
$must($page, [
    '/assets/css/public-donations-allocation.css?v=1.0.0',
    '/assets/js/public-donations-allocation.js?v=1.0.0',
], 'campaign page assets');
$must($installer, [
    "CALL mg_public_donations_append_enum_value('wallet_items', 'source_type', 'public_donation')",
    'CREATE TABLE IF NOT EXISTS campaign_donation_operations',
    'CREATE TABLE IF NOT EXISTS campaign_donation_batches',
    'CREATE TABLE IF NOT EXISTS campaign_donation_rewards',
    'CHECK (recipient_count <= 50)',
    'CHECK (requested_quantity <= 1000)',
    'CHECK (completed_quantity <= requested_quantity)',
], 'Phase 1 allocation schema');
$must($bridge, [
    'mg_zero_reward_issue_from_wallet',
    'recipient_inbox_item_id',
    'microgift_instance_id',
    'pppm_item_db_id',
], 'canonical wallet bridge');

if (str_contains($ui, '.innerHTML')) throw new RuntimeException('Allocation UI must not inject HTML strings.');
if (preg_match('/\b(?:purchase|checkout|payment_intent|charge_customer)\s*\(/i', $engine . "\n" . $endpoint) === 1) {
    throw new RuntimeException('Public Donations allocation must not introduce a public purchase path.');
}
if (!str_contains($engine, 'campaign -> reward template -> assignments -> idempotency operation')) {
    throw new RuntimeException('Deterministic lock order must remain documented.');
}
if (!str_contains($engine, "'preview_reserves_inventory' => false")) {
    throw new RuntimeException('Preflight must state that inventory is not reserved.');
}

echo "Public Donations allocation contracts valid.\n";
