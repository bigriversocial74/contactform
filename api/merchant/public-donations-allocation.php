<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/communications/_communications.php';
require_once dirname(__DIR__) . '/rewards/_wallet_pppm_bridge.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-feature.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-community-assignments.php';
require_once dirname(__DIR__, 2) . '/includes/public-donations-allocation.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'GET'
    ? mg_merchant_require_permission('merchant.campaigns.view')
    : mg_merchant_require_permission('merchant.campaigns.manage');
$merchantId = (int)($user['id'] ?? 0);
$actorId = (int)($user['id'] ?? 0);
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if (!mg_public_donations_is_enabled_for($merchantId, $user)) {
    mg_fail('Public Donations allocation is not enabled for this merchant.', 403);
}

$schemaReady = mg_public_donations_allocation_schema_ready($pdo);
if ($method === 'GET') {
    $campaignRef = strtolower(trim((string)($_GET['campaign_id'] ?? '')));
    $operationRef = strtolower(trim((string)($_GET['operation_id'] ?? '')));
    $campaign = null;
    $assignments = [];
    $recent = [];

    if ($schemaReady && $campaignRef !== '') {
        $campaign = mg_public_donations_allocation_campaign($pdo, $merchantId, $campaignRef, false);
        $assignments = mg_public_donations_assignment_list($pdo, $merchantId, (int)$campaign['id'], 'active', 100);
        $recent = mg_public_donations_allocation_recent($pdo, $merchantId, (int)$campaign['id'], 20);
    }

    mg_ok([
        'schema_ready' => $schemaReady,
        'campaigns' => mg_public_donations_assignment_campaigns($pdo, $merchantId),
        'selected_campaign' => $campaign ? [
            'id' => (string)$campaign['public_id'],
            'slug' => trim((string)$campaign['public_slug']) ?: null,
            'title' => (string)$campaign['title'],
            'status' => (string)$campaign['status'],
            'quantity_limit' => $campaign['quantity_limit'] !== null ? (int)$campaign['quantity_limit'] : null,
            'issued_count' => (int)$campaign['issued_count'],
        ] : null,
        'reward_templates' => $schemaReady ? mg_public_donations_allocation_templates($pdo, $merchantId) : [],
        'active_assignments' => $assignments,
        'recent_operations' => $recent,
        'operation' => $schemaReady && $operationRef !== ''
            ? mg_public_donations_allocation_tracking($pdo, $merchantId, $operationRef)
            : null,
        'limits' => [
            'max_recipients' => 50,
            'max_units' => 1000,
            'large_units' => 100,
            'large_value_cents' => 100000,
        ],
        'architecture' => [
            'wallet' => true,
            'pppm' => true,
            'microgift' => true,
            'inbox' => true,
            'public_purchase' => false,
        ],
    ], $schemaReady
        ? 'Public Donations allocation workspace loaded.'
        : 'Import the Phase 1 Public Donations installer to enable reward allocation.');
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
if (!$schemaReady) mg_fail('Public Donations allocation schema is unavailable. Import the Phase 1 installer.', 503);

$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string)($input['action'] ?? 'preflight')));
if (!in_array($action, ['preflight', 'allocate'], true)) mg_fail('Invalid allocation action.', 422);

$campaignRef = strtolower(trim((string)($input['campaign_id'] ?? '')));
$templateRef = strtolower(trim((string)($input['reward_template_id'] ?? '')));
$recipients = mg_public_donations_allocation_recipients($input['recipients'] ?? null);
$message = mg_public_donations_allocation_text($input['message'] ?? null, 1000, 'Recipient message');
$internalNote = mg_public_donations_allocation_text($input['internal_note'] ?? null, 2000, 'Internal note');

$preflight = mg_public_donations_allocation_preflight(
    $pdo,
    $merchantId,
    $campaignRef,
    $templateRef,
    $recipients,
    $message,
    $internalNote
);

if ($action === 'preflight') {
    mg_ok([
        'preflight' => $preflight,
        'inventory_reserved' => false,
        'reward_inventory_changed' => false,
    ], 'Allocation preview ready. Inventory has not been reserved.');
}

$idempotencyKey = mg_public_donations_allocation_idempotency_key($input['idempotency_key'] ?? null);
$confirmLarge = filter_var($input['confirm_large_operation'] ?? false, FILTER_VALIDATE_BOOLEAN);
$result = mg_public_donations_allocation_execute(
    $pdo,
    $merchantId,
    $actorId,
    $campaignRef,
    $templateRef,
    $recipients,
    $idempotencyKey,
    $message,
    $internalNote,
    $confirmLarge
);

if (function_exists('mg_audit')) {
    mg_audit('merchant.public_donations.allocate', 'campaign', [
        'campaign_id' => (string)$result['campaign']['id'],
        'operation_id' => (string)$result['id'],
        'reward_template_id' => (string)$result['reward_template']['id'],
        'recipient_count' => (int)$result['recipient_count'],
        'quantity' => (int)$result['completed_quantity'],
        'duplicate' => !empty($result['duplicate']),
    ], $actorId);
}

mg_ok([
    'operation' => $result,
    'duplicate' => !empty($result['duplicate']),
    'reward_inventory_changed' => empty($result['duplicate']),
], !empty($result['duplicate'])
    ? 'This allocation was already completed; no duplicate rewards were issued.'
    : 'Rewards were allocated through Wallet, PPPM, Microgift, and Inbox.');
