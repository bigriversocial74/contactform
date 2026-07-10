<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function read_contract(string $path): string
{
    $content = file_get_contents($path);
    if ($content === false) throw new RuntimeException('Unable to read ' . $path);
    return $content;
}

function require_contract(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) $failures[] = $message;
}

$page = read_contract($root . '/merchant-canvas.php');
$runtime = read_contract($root . '/assets/js/merchant-canvas-manual-operations.js');
$containment = read_contract($root . '/assets/js/merchant-canvas-containment.js');
$activeUsers = read_contract($root . '/api/merchant-canvas/active-users.php');
$customerCrm = read_contract($root . '/api/merchant-canvas/customer-crm.php');
$customerCrmUpdate = read_contract($root . '/api/merchant-canvas/customer-crm-update.php');
$sendMessage = read_contract($root . '/api/merchant-canvas/send-message.php');
$sendReward = read_contract($root . '/api/merchant-canvas/send-reward.php');
$messaging = read_contract($root . '/api/store/_canvas_messaging.php');
$manualOps = read_contract($root . '/api/store/_canvas_manual_operations.php');
$health = read_contract($root . '/api/merchant-canvas/health.php');
$sql = read_contract($root . '/database/merchant_canvas_manual_operations_stabilization_v1.sql');

require_contract(str_contains($page, '/assets/js/merchant-canvas-manual-operations.js'), 'Merchant Canvas must load the stabilized runtime.');
require_contract(!str_contains($page, "'/assets/js/merchant-canvas.js'"), 'Merchant Canvas must not load the legacy polling runtime.');
require_contract(str_contains($page, 'role="dialog"') && str_contains($page, 'aria-modal="true"'), 'Customer CRM drawer must expose dialog semantics.');
require_contract(str_contains($runtime, 'AbortController'), 'Runtime must cancel stale requests.');
require_contract(str_contains($runtime, 'visibilitychange'), 'Runtime must pause while the tab is hidden.');
require_contract(!str_contains($runtime, 'setInterval('), 'Runtime must not use overlapping interval polling.');
require_contract(str_contains($runtime, 'stableActionKey'), 'Runtime must preserve idempotency keys across retries.');
require_contract(str_contains($runtime, 'trapDrawerFocus'), 'Runtime must trap focus inside the CRM drawer.');
require_contract(str_contains($runtime, 'requestId !== state.selectedRequest'), 'Runtime must reject stale CRM responses.');
require_contract(str_contains($activeUsers, 'mg_merchant_canvas_expire_sessions'), 'Active users endpoint must expire stale sessions.');
require_contract(str_contains($activeUsers, 'mg_store_close_session_row'), 'Session expiry must use the canonical close helper.');
require_contract(str_contains($customerCrm, "'crm' => $crm"), 'Customer CRM response must include durable CRM safeguards.');
require_contract(str_contains($customerCrmUpdate, 'mg_store_manual_ops_crm_save'), 'Customer CRM update endpoint must persist safeguards.');
require_contract(str_contains($sendMessage, 'idempotency_key'), 'Manual messaging endpoint must require an idempotency key.');
require_contract(str_contains($messaging, 'mg_store_manual_ops_receipt_claim'), 'Canonical messaging must claim a transactional action receipt.');
require_contract(str_contains($messaging, 'mg_store_manual_ops_assert_message_allowed'), 'Canonical messaging must enforce Do Not Message inside the delivery transaction.');
require_contract(str_contains($sendReward, 'GET_LOCK'), 'Manual rewards must serialize concurrent retries.');
require_contract(str_contains($sendReward, 'manual_reward'), 'Manual rewards must record durable action receipts.');
require_contract(str_contains($manualOps, 'mg_merchant_customer_crm'), 'Manual operations helper must use the CRM table.');
require_contract(str_contains($manualOps, 'mg_merchant_canvas_action_receipts'), 'Manual operations helper must use the action receipt table.');
require_contract(str_contains($sql, 'CREATE TABLE IF NOT EXISTS mg_merchant_customer_crm'), 'Migration must create the CRM table.');
require_contract(str_contains($sql, 'CREATE TABLE IF NOT EXISTS mg_merchant_canvas_action_receipts'), 'Migration must create the action receipt table.');
require_contract(!str_contains($health, 'SELECT DATABASE()'), 'Merchant health endpoint must not disclose the database name.');
require_contract(!str_contains($health, 'PDO::ATTR_DRIVER_NAME'), 'Merchant health endpoint must not disclose the database driver.');
require_contract(str_contains($containment, '/api/merchant-canvas/auto-chat.php'), 'Containment must continue blocking automatic chat.');
require_contract(!str_contains($containment, 'loadRewardOptions'), 'Containment must not mutate stabilized reward forms.');

if ($failures !== []) {
    fwrite(STDERR, "Merchant Canvas Manual Operations Stabilization v1 validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Merchant Canvas Manual Operations Stabilization v1 validation passed.\n");
