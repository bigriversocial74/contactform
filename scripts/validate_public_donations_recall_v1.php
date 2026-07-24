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

$core = $read('includes/public-donations-recall.php');
$endpoint = $read('api/merchant/public-donations-recall.php');
$ui = $read('assets/js/public-donations-recall.js');
$styles = $read('assets/css/public-donations-recall.css');
$page = $read('merchant-campaigns.php');
$installer = $read('database/20260724_public_donations_community_v1_single_install.sql');
$lifecycle = $read('api/microgifts/_lifecycle.php');
$projection = $read('api/microgifts/_action_center_projection.php');

$must($core, [
    'mg_public_donations_recall_classify',
    "? 'recallable' : 'unavailable'",
    "return 'regifted'",
    "return 'claimed'",
    "return 'redeemed'",
    "return 'expired'",
    "return 'already_recalled'",
    'downstream_recipients_protected',
    'beginTransaction()',
    'FOR UPDATE',
    'hash_equals',
    "operation_kind'] !== 'recall'",
    "'partial_recall'",
    "status='cancelled'",
    'mg_pppm_record_event',
    'mg_microgift_apply_lifecycle',
    'mg_action_center_project_lifecycle',
    "status='recalled'",
    'GREATEST(issued_count-?,0)',
    "'partially_recalled'",
    "'recalled'",
    'mg_create_notification',
    'rollBack()',
    "VALUES (?,?,?,?,'recall','partial_recall','processing',?,?,?,?,0,?,?,?,?,'standard',?,?,?,NOW(),NOW())",
], 'recall core');
$must($endpoint, [
    'merchant.campaigns.view',
    'merchant.campaigns.manage',
    'mg_require_csrf_for_write',
    'mg_public_donations_recall_preview',
    'mg_public_donations_recall_execute',
    "'downstream_recipients_affected' => false",
    'mg_audit',
    'mg_security_log',
], 'recall endpoint');
$must($ui, [
    'Recall untouched Community rewards',
    'Refresh preview',
    'Recall eligible rewards',
    'maximum_recall_quantity',
    'regifted',
    'claimed',
    'redeemed',
    'expired',
    'already_recalled',
    "Microgifter.post('/api/merchant/public-donations-recall.php'",
    'window.confirm',
    'replaceChildren',
], 'recall UI');
$must($styles, ['mg-donation-recall-card', 'mg-donation-recall-metrics', 'mg-btn-danger'], 'recall styles');
$must($page, [
    '/assets/css/public-donations-recall.css?v=1.0.0',
    '/assets/js/public-donations-recall.js?v=1.0.0',
], 'campaign page assets');
$must($installer, [
    "operation_kind ENUM('allocation','recall')",
    "operation_mode ENUM('single','same_quantity','custom_quantity','partial_recall')",
    "status ENUM('allocated','partially_recalled','recalled')",
    "status ENUM('allocated','recalled')",
    'recalled_quantity',
    'recalled_at',
    'recalled_by_user_id',
    'recall_reason',
], 'Phase 1 recall schema');
$must($lifecycle, ["'cancel'=>'cancelled'", 'microgift_lifecycle_actions'], 'canonical Microgift lifecycle');
$must($projection, ["return 'revoked'", 'mg_action_center_project_lifecycle'], 'Action Center lifecycle projection');

if (str_contains($ui, '.innerHTML')) throw new RuntimeException('Recall UI must not inject HTML strings.');
if (preg_match('/\b(?:purchase|checkout|payment_intent|charge_customer)\s*\(/i', $core . "\n" . $endpoint) === 1) {
    throw new RuntimeException('Recall controls must not introduce a purchase path.');
}
if (!str_contains($core, 'batch/campaign/template -> rewards -> idempotency operation')) {
    throw new RuntimeException('Recall lock order must remain documented.');
}

echo "Public Donations recall contracts valid.\n";
